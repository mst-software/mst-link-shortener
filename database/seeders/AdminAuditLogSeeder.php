<?php

namespace Database\Seeders;

use App\Models\AdminAuditLog;
use Illuminate\Database\Seeder;

class AdminAuditLogSeeder extends Seeder
{
    /**
     * Seed a few example audit entries for local development. Not called from DatabaseSeeder,
     * because a real audit log must only contain actions that really happened.
     */
    public function run(): void
    {
        AdminAuditLog::factory()->count(5)->create();
    }
}
