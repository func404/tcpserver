<?php
namespace lib;

class Daemon
{

    private $pidFile = '';

    private $processTitle = '';

    public function __construct($process_title, $pid_file)
    {
        if ($process_title) {
            $this->processTitle = $process_title;
        } else {
            $this->processTitle = __FILE__;
        }
        if ($pid_file) {
            $this->pidFile = '/var/run/' . $pid_file .'.pid';
        } else {
            $this->pidFile = '/var/run/' . $this->process_title . '.pid';
        }
    }

    public static function run($process_title = '', $pid_file = '')
    {
        return new self($process_title, $pid_file);
    }

    public function init($argc,$argv)
    {
        if (file_exists($this->pidFile)) {
            $pid = trim(file_get_contents($this->pidFile));
        } else {
            $handle = fopen($this->pidFile, 'w');
            $pid = 0;
            fclose($handle);
        }
        if ($argc < 2) {
            $action = 'start';
        } else {
            $action = $argv[1];
        }
        
        if ($action == 'stop') {
            if ($pid) {
                exec('ps p ' . $pid, $tmp);
                if (count($tmp) > 1) {
                    $rst = posix_kill($pid, 9);
                    fwrite(STDOUT, 'Process is killed ' . $pid . "\n");
                } else {
                    fwrite(STDOUT, 'Pid is not exists: ' . $pid . "\n");
                }
                $handle = fopen(PID_FILE, 'w');
                fclose($handle);
                exit();
            } else {
                fwrite(STDOUT, "Process is not exists\n");
            }
        } else {
            if ($pid) {
                exec('ps p ' . $pid, $tmp);
                $next = 0;
                if (count($tmp) > 1) {
                    fwrite(STDOUT, "This Process is runing[{$pid}],please input 1 [skip and exit] ,or 2 [kill and start again] ,default 1: ");
                    $next = trim(fgets(STDIN));
                    
                    if ($next == 2) {
                        posix_kill($pid, 9);
                    } else {
                        fwrite(STDOUT, 'Process is running ' . $pid . "[not restart!]\n");
                        exit();
                    }
                }
            }
            // get input
        }
        
        $pid = pcntl_fork();
        if (- 1 === $pid) {
            throw new \Exception('fork fail');
        } elseif ($pid > 0) {
            exit(0);
        }
        if (- 1 === posix_setsid()) {
            throw new \Exception("setsid fail");
        }
        // Fork again avoid SVR4 system regain the control of terminal.
        $pid = pcntl_fork();
        if (- 1 === $pid) {
            throw new \Exception("fork fail");
        } elseif (0 !== $pid) {
            exit(0);
        }
        $handle = fopen($this->pidFile, 'w');
        fwrite($handle, posix_getpid());
        fclose($handle);
        fwrite(STDOUT, 'Process is running ' . $this->processTitle . '  ' . posix_getpid() . "\n");
    }
}
