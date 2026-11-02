# Bulkstream · Cargo Handling System

Technical assessment for the Bulkstream Limited **Systems Developer** role.

A Laravel 13 + FilamentPHP 5 control panel for a bulk liquid port terminal, tracking
**CPO, CPOL and PFAD** along the workflow

**Vessel discharge (inbound) → shore storage tanks → truck delivery (outbound)**

- **Live demo:** https://bulkstream.139-162-184-72.sslip.io (credentials sent with the submission email)
- **Stack:** PHP 8.4 · Laravel 13 · Filament 5 · MySQL 8 · queue worker + scheduler

---

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
- **Tanks, vessels, users** with role-based access, **Settings** (loss rate, approval threshold, alarm levels) and an append-only **Audit log**.

| Role | Can |
|---|---|
| Operator | Record discharges and loadings, request stock corrections |
| Supervisor | Above, plus approve or decline releases and corrections, manage vessels, view the audit log |
| Admin | Everything, plus tanks, users and terminal settings |

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

43 tests, 133 assertions: domain unit tests with in-memory fakes, feature tests against MySQL,
and a multi-process concurrency test.

---

© 2026 Martin Kiragu. Provided for evaluation only; see [LICENSE](LICENSE).
