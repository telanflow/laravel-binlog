<?php

namespace Telanflow\Binlog\Commands;

use Swoole\Process;
use Illuminate\Console\Command;
use Telanflow\Binlog\Configure\Configure;
use Telanflow\Binlog\Server\Manager;
use Telanflow\Binlog\Server\PidManager;
use Illuminate\Support\Facades\Config;

class BinlogCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mysql:binlog {action : start|stop|restart|infos}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mysql binlog server.';

    /**
     * The console command action. start|stop|restart|infos
     *
     * @var string
     */
    protected $action;

    /**
     * @var int
     */
    protected $currentPid;

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $this->loadConfig();
        $this->initAction();
        $this->runAction();
    }

    /**
     * Load config
     */
    protected function loadConfig()
    {
        /** @var Config $configFacade **/
        $configFacade = $this->laravel->make('config');
        $binlogConf = $configFacade->get('binlog');
        Configure::parse($binlogConf);
    }

    /**
     * Initialize command action.
     */
    protected function initAction()
    {
        $this->action = $this->argument('action');
        if (! in_array($this->action, ['start', 'stop', 'restart', 'infos'], true)) {
            $this->error(
                "Invalid argument '{$this->action}'. Expected 'start', 'stop', 'restart' or 'infos'."
            );
            return;
        }
    }

    /**
     * Run action.
     */
    protected function runAction()
    {
        $this->{$this->action}();
    }

    /**
     * Start listen binlog.
     */
    protected function start()
    {
        if ($this->isRunning($this->getCurrentPid())) {
            $this->error('Failed! mysql_binlog process is already running.');
            return;
        }

        $this->info('Starting mysql binlog server...');
        $this->info("Mysql binlog server started");
        if (Configure::isDaemon())
        {
            $this->info(
                '> (You can run this command to ensure the ' .
                'mysql_binlog process is running: ps aux|grep '.Configure::getProcessName().')'
            );

            Process::daemon(true, false);
        }

        /** @var Manager $manager */
        $manager = $this->laravel->make(Manager::class);
        $manager->run();
    }

    /**
     * Stop laravel_binlog_server.
     */
    protected function stop()
    {
        $pid = $this->getCurrentPid();

        if (! $this->isRunning($pid)) {
            $this->error("Failed! There is no mysql_binlog process running.");
            return;
        }

        $this->info('Stopping mysql binlog server...');
        $isRunning = $this->killProcess($pid, SIGTERM, 15);
        if ($isRunning) {
            $this->error('Unable to stop the mysql_binlog process.');
            return;
        }

        // Remove the pid file.
        $this->laravel->make(PidManager::class)->delete();
        $this->info('> success');
    }

    /**
     * Restart laravel binlog server.
     */
    protected function restart()
    {
        if ($this->isRunning($this->getCurrentPid())) {
            $this->stop();
        }
        $this->start();
    }

    /**
     * Display PHP and Swoole miscs infos.
     */
    protected function infos()
    {
        $pid = $this->getCurrentPid();
        $isRunning = $this->isRunning($pid);

        $table = [
            ['PHP Version', 'Version' => phpversion()],
            ['Swoole Version', 'Version' => swoole_version()],
            ['Laravel Version', $this->getApplication()->getVersion()],
            ['Server Status', $isRunning ? 'Online' : 'Offline'],
            ['PID', $isRunning ? $pid : 'None'],
        ];

        $this->table(['Name', 'Value'], $table);
    }

    /**
     * Get pid.
     *
     * @return int|null
     */
    protected function getCurrentPid()
    {
        if ($this->currentPid) {
            return $this->currentPid;
        }

        /** @var PidManager $pidManager */
        $pidManager = $this->laravel->make(PidManager::class);
        $this->currentPid = $pidManager->read();

        return $this->currentPid;
    }

    /**
     * If Binlog process is running.
     *
     * @param int $pid
     * @return bool
     */
    protected function isRunning($pid)
    {
        if (! $pid) {
            return false;
        }

        try {
            return Process::kill($pid, 0);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Kill process.
     *
     * @param int $pid
     * @param int $sig
     * @param int $wait
     * @return bool
     */
    protected function killProcess($pid, $sig, $wait = 0)
    {
        Process::kill($pid, $sig);

        if ($wait) {
            $start = time();

            do {
                if (! $this->isRunning($pid)) {
                    break;
                }

                usleep(100000);
            } while (time() < $start + $wait);
        }

        return $this->isRunning($pid);
    }

}
