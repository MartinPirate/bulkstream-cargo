# Bulkstream · Cargo Handling System

Technical assessment for the Bulkstream Limited **Systems Developer** role.

A Laravel 13 + FilamentPHP 5 control panel for a bulk liquid port terminal, tracking
**CPO, CPOL and PFAD** along the workflow

**Vessel discharge (inbound) → shore storage tanks → truck delivery (outbound)**

- **Live demo:** https://bulkstream.139-162-184-72.sslip.io (credentials sent with the submission email)
- **Stack:** PHP 8.4 · Laravel 13 · Filament 5 · MySQL 8 · queue worker + scheduler

---

## Brief compliance checklist

Every requirement in the assessment brief, and where it is implemented.

| # | Requirement from the brief | Status | Where it lives |
|---|---|---|---|
| 1 | Workflow: vessel discharge → shore tanks → truck delivery | ✅ Done | `MovementDirection` enum (Inbound = vessel, Outbound = truck); `CargoMovement`, `Vessel`, `Tank` models |
| 2 | Commodity: Crude Palm Oil (CPO), temperature-controlled, viscous | ✅ Done | `Commodity::CPO`: heated, 50–55 °C band, handling note; tunable in **Commodity profiles** |
| 3 | Commodity: Crude Palm Olein (CPOL), higher fluidity | ✅ Done | `Commodity::CPOL`: ambient, 25–35 °C |
| 4 | Commodity: Palm Fatty Acid Distillate (PFAD), viscous byproduct | ✅ Done | `Commodity::PFAD`: heated, 60–70 °C |
| 5 | Capacity boundaries, with a custom domain exception on overflow | ✅ Done | `TankState::receive()` throws `TankCapacityExceededException`; MySQL `CHECK (balance_kg <= capacity_kg)` refuses overfills even if code is bypassed |
| 6 | 0.5% transit loss on all inbound vessel flows | ✅ Done | `TransitLossPolicy`, applied to inbound only (`TerminalEngine.php:79`); default 0.50%, admin-configurable |
| 7 | Out-of-order logs re-sorted by timestamp before balances change | ✅ Done | `MovementSequencer`: event time to the microsecond, then a fixed tie-break |
| 8 | Atomic operations: no race conditions or duplicate allocation | ✅ Done | `SELECT … FOR UPDATE` in a transaction with a fixed lock order, idempotent `claim()`, `WithoutOverlapping` queue job; `ConcurrencyTest` races 20 OS processes into one tank |
| 9 | Laravel + FilamentPHP, not procedural scripts | ✅ Done | Laravel 13 + Filament 5 |
| 10 | Reactive frontend handlers | ✅ Done | 11 `live()` / `afterStateUpdated` handlers; movement and stock-correction forms preview loss, net, ullage and overflow as you type |
| 11 | Backend entity state hooks | ✅ Done | `TankObserver` (blocks direct balance edits, capacity below stock, re-grading a full tank) and `CargoMovementObserver` (routing, dispatch after commit, immutability) |
| 12 | Clear domain separation of concerns | ✅ Done | `packages/tank-terminal` has no Laravel imports; `app/Terminal` adapts it through ports |
| 13 | Operational control dashboard | ✅ Done | Live widgets (stock per commodity, tank levels, alerts, recent movements), approvals, stock corrections, audit log |
| 14 | Repository with the complete solution | ✅ Done | This repository |
| 15 | Live demo environment | ✅ Done | https://bulkstream.139-162-184-72.sslip.io |

## The four domain rules: where each one lives

| Rule from the brief | Implementation | Proven by |
|---|---|---|
| **Capacity boundaries**: a tank can never exceed its volume; forcing an overflow raises a *custom domain exception* | `TankState::receive()` throws `TankCapacityExceededException` (subclass of `TerminalException`). A MySQL `CHECK (balance_kg <= capacity_kg)` refuses overfills even if application code is bypassed. | `TankStateTest`, `CargoPipelineTest::test_overflow_is_rejected…`, `…database_refuses_an_overfilled_tank…` |
| **Volumetric loss adjustment**: 0.5% transit loss on all inbound vessel flows | `TransitLossPolicy` (integer basis points, half-up rounding, `net + loss == gross` always). The engine applies it only to inbound flows. The rate is admin-configurable (default 0.50%). | `TransitLossPolicyTest`, `CargoPipelineTest::test_inbound_discharge_applies_net…` |
| **Time-series synchronisation**: logs arrive out of order; re-sort by timestamp before touching balances | Movements are stored as `pending` with the source `occurred_at` (microsecond precision). `MovementSequencer` orders them by event time (inbound before outbound on ties, then id) before the engine applies anything. | `TerminalEngineTest::test_out_of_order_events_are_resequenced…`, `CargoPipelineTest::test_events_logged_out_of_order…` |
| **Atomic operations**: no race conditions or duplicate allocation of capacity | Each movement is applied in its own transaction holding `SELECT … FOR UPDATE` locks on the movement row and then the tank row (fixed lock order, so no deadlock cycle). `claim()` makes processing idempotent. The queue job runs `WithoutOverlapping`. | `ConcurrencyTest`: 20 OS processes race 100 MT parcels into a 1,000 MT tank; exactly 10 land (995 MT). The same movement sent to 8 workers is applied once. |

## Architecture

```
packages/tank-terminal/            ← framework-agnostic domain core (no Laravel imports)
  Domain/ Quantity, TankState, TransitLossPolicy, MovementSequencer, CargoMovement
  Contracts/ TankRepository, MovementLedger, TransactionManager   (ports)
  Exceptions/ TerminalException → TankCapacityExceeded, InsufficientTankStock, CommodityMismatch
  TerminalEngine                   ← sequence → lock → adjust → guard → persist

app/Terminal/                      ← adapters + application services
  EloquentTankRepository           SELECT … FOR UPDATE, the only writer of tanks.balance_kg
  EloquentMovementLedger           claim / markApplied / markRejected
  CargoPipeline                    processPending(), approve(), decline()
  StockCorrectionService           maker-checker book adjustments through the same TankState
app/Observers/                     ← backend entity state hooks
  TankObserver                     blocks direct balance edits, capacity < stock, re-grading a full tank
  CargoMovementObserver            normalises, routes large releases to approval, dispatches the
                                   pipeline after commit, makes processed rows immutable
app/Jobs/ProcessCargoPipeline      unique-until-processing + WithoutOverlapping queue job
app/Filament/                      ← operational control dashboard
```

Quantities are held as **integer kilograms** end to end (displayed as metric tonnes), so
thousands of movements can be summed without floating-point drift.

## Control panel

- **Dashboard**, polling every 10 s: stock and fill per commodity, tank level chart, tank alerts (high/low level, product outside its storage temperature band), latest processed movements, approvals waiting, blocked attempts in the last 24 h.
- **Cargo movements**: a reactive entry form that previews transit loss, net quantity, ullage after, overflow and approval routing as you type. Status tabs, filters, and a **Simulate pipeline feed** action that emits a burst of out-of-order meter and weighbridge events so you can watch the sequencer and guards work.
- **Approvals (maker-checker)**: releases at or above a configurable threshold (default 100 MT) wait for a supervisor, who can never approve their own entry. Approve and Decline actions carry notes.
- **Stock corrections**: dip or ullage survey adjustments, requested by anyone and applied only after supervisor approval, through the same capacity and stock guards.
- **Commodity profiles**: per-product storage band, heating and handling notes. Codes are fixed by the domain; admins tune the handling, and tank temperature alerts follow it.
- **Tanks, vessels, users** with role-based access, **Settings** (loss rate, approval threshold, alarm levels) and an append-only **Audit log**.

## Roles and permissions

Three fixed roles, each building on the one below: **Operator → Supervisor → Admin**.
Enforced server-side by Laravel policies (`app/Policies`) and re-checked inside the
application services, so hiding a button is never the only guard.

- **Operator** (floor staff, the *maker*): records vessel discharges and truck loadings, requests stock corrections, and can correct their own release while it still awaits approval. Sees dashboard, tanks, vessels and commodity profiles read-only.
- **Supervisor** (shift lead, the *checker*): everything an Operator can do, plus approve or decline large releases (≥ 100 MT by default) and stock corrections, add and edit vessels, run *Simulate pipeline feed* / *Process pending now*, and read the audit log.
- **Admin** (terminal manager): everything a Supervisor can do, plus tanks (deletable only when empty), commodity profiles, users and terminal settings, and deleting movements that are still pending or were declined.

| Action | Operator | Supervisor | Admin |
|---|:-:|:-:|:-:|
| Record discharge / loading | ✅ | ✅ | ✅ |
| Request stock correction | ✅ | ✅ | ✅ |
| Approve or decline releases and corrections | ❌ | ✅ | ✅ |
| Manage vessels | ❌ | ✅ | ✅ |
| Simulate / process pipeline | ❌ | ✅ | ✅ |
| Audit log | ❌ | ✅ | ✅ |
| Tanks, commodity profiles | view | view | ✅ |
| Users, terminal settings | ❌ | ❌ | ✅ |

Rules no role can bypass:

- **Maker-checker:** nobody approves their own entry, Admin included. A second person always signs off.
- **Stock is never typed in.** Balances change only through movements and approved corrections, which pass the same capacity and stock guards.
- **History is immutable.** Processed movements cannot be edited or deleted, and the audit log is append-only.
- A refused screen returns a real `403` with a branded page that tells the user their role and who can help.

Every row of the table, both the allowed and the refused side, is covered by `tests/Feature/RolePermissionsTest.php`.

## Run locally

```bash
composer install
cp .env.example .env && php artisan key:generate
# set DB_* for a MySQL 8 database and DEMO_PASSWORD
php artisan migrate --seed
php artisan serve            # http://localhost:8000/admin
php artisan queue:work       # applies pending movements
```

Seeded users: `admin@bulkstream.test`, `supervisor@bulkstream.test`, `operator@bulkstream.test` (password = `DEMO_PASSWORD`).

## Tests

```bash
# uses the MySQL database "bulkstream_cargo_test" (see phpunit.xml)
php artisan test
```

63 tests, 245 assertions:

| Suite | Covers |
|---|---|
| `tests/Unit/TankTerminal` | Domain core with in-memory fakes: capacity guard, transit-loss rounding, out-of-order sequencing |
| `tests/Feature/Terminal` | Pipeline against MySQL, stock corrections (maker-checker), multi-process concurrency |
| `tests/Feature/CommodityProfileTest` | Profile seeding and fallback, temperature-band DB check, edit permissions, HTML escaping |
| `tests/Feature/PanelAccessTest` | Role-based access to every screen |
| `tests/Feature/RolePermissionsTest` | The full role matrix (allowed and refused), maker-checker for every approving role, branded 403/404 pages |

---

© 2026 Martin Kiragu. Provided for evaluation only; see [LICENSE](LICENSE).
