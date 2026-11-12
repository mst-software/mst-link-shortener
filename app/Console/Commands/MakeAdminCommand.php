<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class MakeAdminCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:admin {username} {email} {--password= : The password (asked for when omitted)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create an administrator account';

    public function handle(): int
    {
        $password = $this->option('password') ?: $this->secret('Password');

        $validator = Validator::make([
            'username' => $this->argument('username'),
            'email' => $this->argument('email'),
            'password' => $password,
        ], [
            'username' => ['required', 'string', 'min:3', 'max:32', 'regex:/^[A-Za-z0-9_]+$/', Rule::unique(User::class, 'username')],
            'email' => ['required', 'string', 'email', 'max:254', Rule::unique(User::class, 'email')],
            'password' => ['required', 'string', 'min:8'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = new User([
            'username' => $this->argument('username'),
            'email' => mb_strtolower((string) $this->argument('email')),
            'password' => $password,
        ]);
        $user->role = UserRole::Admin;
        $user->email_verified_at = now();
        $user->save();

        $this->info("Administrator {$user->username} created.");

        return self::SUCCESS;
    }
}
