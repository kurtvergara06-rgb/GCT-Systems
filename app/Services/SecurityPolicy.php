<?php
namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

class SecurityPolicy
{
    public static function defaults(): array
    {
        // Keep the existing rules until an authorized admin explicitly saves.
        return [
            'minimum_password_length' => 8,
            'password_expiration' => 0,
            'require_uppercase' => false,
            'require_number' => false,
            'require_special' => false,
            'session_timeout' => 0,
            'allow_multiple_sessions' => true,
            'logout_on_password_change' => false,
            'failed_attempts' => 5,
            'lockout_duration' => 5,
        ];
    }

    public static function current(): array
    {
        return Cache::remember('security-policy.v1', 30, static function (): array {
            try {
                $stored = json_decode((string) DB::table('system_settings')->where('id', 1)->value('security_policy'), true);
                return array_replace(self::defaults(), is_array($stored) ? array_intersect_key($stored, self::defaults()) : []);
            } catch (\Illuminate\Database\QueryException $exception) {
                return self::defaults();
            }
        });
    }

    public static function clearCache(): void { Cache::forget('security-policy.v1'); }

    public static function passwordRules(bool $requireConfirmation = true): array
    {
        $policy = self::current();
        $rules = ['required'];
        if ($requireConfirmation) $rules[] = 'confirmed';
        $rules[] = Password::min((int) $policy['minimum_password_length']);
        if ($policy['require_uppercase']) $rules[] = 'regex:/[A-Z]/';
        if ($policy['require_number']) $rules[] = 'regex:/[0-9]/';
        if ($policy['require_special']) $rules[] = 'regex:/[^A-Za-z0-9]/';
        return $rules;
    }

    public static function revokeOtherSessions(int $userId, ?string $except = null): void
    {
        if (config('session.driver') !== 'database') return;
        $query = DB::table((string) config('session.table', 'sessions'))->where('user_id', $userId);
        if ($except !== null) $query->where('id', '!=', $except);
        $query->delete();
    }
}
