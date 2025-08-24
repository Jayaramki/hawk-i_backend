<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ADOProject;

class CheckProjects extends Command
{
    protected $signature = 'check:projects';
    protected $description = 'Check projects in database';

    public function handle()
    {
        $this->info('Projects in database:');
        $projects = ADOProject::all(['id', 'name']);
        
        if ($projects->isEmpty()) {
            $this->warn('No projects found in database!');
            return;
        }
        // I want to print this $projects array
        $this->info('Projects:');
        $this->info(json_encode($projects));
        foreach ($projects as $project) {
            $this->line("{$project->id} - {$project->name}");
        }
    }
}
