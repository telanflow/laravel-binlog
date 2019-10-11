<?php

namespace Telanflow\Binlog\Server;

class PidManager
{
    /**
     * @var string
     */
    protected $pidFile;

    public function __construct(string $pidFile = null)
    {
        $this->setPidFile(
            $pidFile ?: sys_get_temp_dir() . '/binlog.pid'
        );
    }

    /**
     * Set pid file path
     */
    public function setPidFile(string $pidFile): self
    {
        $this->pidFile = $pidFile;

        return $this;
    }

    /**
     * Write master pid to pid file
     *
     * @param int $masterPid
     */
    public function write($masterPid): void
    {
        if (! is_writable($this->pidFile)
            && ! is_writable(dirname($this->pidFile))
        ) {
            throw new \RuntimeException(
                sprintf('Pid file "%s" is not writable', $this->pidFile)
            );
        }

        file_put_contents($this->pidFile, $masterPid);
    }

    /**
     * Read master pid from pid file
     *
     * @return int|null masterPid
     */
    public function read(): ?int
    {
        if (is_readable($this->pidFile)) {
            $pid = file_get_contents($this->pidFile);
        }

        return isset($pid) ? intval($pid) : null;
    }

    /**
     * Gets pid file path.
     *
     * @return string
     */
    public function file()
    {
        return $this->pidFile;
    }

    /**
     * Delete pid file
     */
    public function delete(): bool
    {
        if (is_writable($this->pidFile)) {
            return unlink($this->pidFile);
        }

        return false;
    }
}
