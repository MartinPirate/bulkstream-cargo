<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

/**
 * Login page for the assessment demo: when DEMO_LOGIN is on, the admin test
 * account is pre-filled and all three role accounts are listed, so reviewers
 * can sign in straight away. Off by default; nothing changes in a real install.
 */
class DemoLogin extends Login
{
    public function mount(): void
    {
        parent::mount();

        if ($this->demoEnabled()) {
            $this->form->fill([
                'email' => 'admin@bulkstream.test',
                'password' => config('app.demo_password'),
                'remember' => true,
            ]);
        }
    }

    public function getSubheading(): string|Htmlable|null
    {
        if (! $this->demoEnabled()) {
            return parent::getSubheading();
        }

        $password = e((string) config('app.demo_password'));
        $row = fn (string $role, string $email) => "<tr><td style=\"padding:2px 12px 2px 0;opacity:.7\">{$role}</td><td style=\"font-family:ui-monospace,monospace\">{$email}</td></tr>";

        return new HtmlString(
            '<div style="text-align:left;font-size:.85rem;margin-top:.5rem;padding:.75rem 1rem;border:1px solid #e4e4e7;background:#fafafa">'
            .'<strong>Test accounts</strong> (admin is pre-filled)'
            .'<table style="margin-top:.4rem">'
            .$row('Admin', 'admin@bulkstream.test')
            .$row('Supervisor', 'supervisor@bulkstream.test')
            .$row('Operator', 'operator@bulkstream.test')
            ."<tr><td style=\"padding:6px 12px 0 0;opacity:.7\">Password</td><td style=\"padding-top:6px;font-family:ui-monospace,monospace\">{$password}</td></tr>"
            .'</table></div>'
        );
    }

    private function demoEnabled(): bool
    {
        return (bool) config('app.demo_login') && filled(config('app.demo_password'));
    }
}
