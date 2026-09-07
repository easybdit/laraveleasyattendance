<?php

namespace Easybdit\LaravelEasyAttendance\Console\Commands;

use Easybdit\LaravelEasyAttendance\Models\Employee;
use Easybdit\LaravelEasyAttendance\Services\SalaryService;
use Illuminate\Console\Command;

class GenerateSalary extends Command
{
    protected $signature = 'attendance:generate-salary {year} {month} {--employee= : Generate for one employee id only, default all active}';

    protected $description = 'Generate salary slips for a month (rebuilds that month\'s attendance summaries first)';

    public function handle(SalaryService $service): int
    {
        $year = (int) $this->argument('year');
        $month = (int) $this->argument('month');

        if ($employeeId = $this->option('employee')) {
            $employee = Employee::findOrFail($employeeId);
            $slip = $service->generate($employee, $year, $month);
            $this->components->info("Generated slip for {$employee->name}: net {$slip->net_salary}.");

            return self::SUCCESS;
        }

        $slips = $service->generateForMonth($year, $month);
        $this->components->info(count($slips)." slip(s) generated for {$year}-{$month}.");

        return self::SUCCESS;
    }
}
