<?php

/**
 * Multiple Processes calculation Extension
 *
 * Author Jerry Shaw <jerry-shaw@live.com>
 * Author 秋水之冰 <27206617@qq.com>
 *
 * Copyright 2017 Jerry Shaw
 * Copyright 2017 秋水之冰
 *
 * This file is part of NervSys.
 *
 * NervSys is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * NervSys is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with NervSys. If not, see <http://www.gnu.org/licenses/>.
 */

namespace ext;

use core\ctr\os;
use core\ctr\router\cli;

class mpc
{
    //Wait for process result
    public static $wait = true;

    //Wait timeout (in microseconds)
    public static $wait_time = 10000;

    //Process timeout (in microseconds, "0" means wait till done)
    public static $process_time = 0;

    //PHP cmd key name in "cfg.ini"
    public static $php_key = 'php';

    //PHP executable path in "cfg.ini"
    public static $php_exe = '';

    //Max running processes
    public static $max_runs = 10;

    //Process jobs
    private static $jobs = [];

    //Resource list
    private static $resource = [];

    /**
     * Begin process
     */
    public static function begin(): void
    {
        //Reset list
        self::$jobs = [];
        self::$resource = [];
    }

    /**
     * Add to process list
     *
     * @param string $cmd
     * @param array  $argv
     */
    public static function add(string $cmd, array $argv): void
    {
        self::$jobs[] = ['cmd' => &$cmd, 'arg' => &$argv];
    }

    /**
     * Commit to process
     */
    public static function commit(): array
    {
        if (empty(self::$jobs) || !self::get_php()) return [];

        $result = [];
        $job_list = count(self::$jobs) < self::$max_runs ? [self::$jobs] : array_chunk(self::$jobs, self::$max_runs, true);

        foreach ($job_list as $jobs) {
            //Copy jobs
            self::$jobs = $jobs;

            //Execute processes
            self::execute();

            //Check wait options
            if (!self::$wait) return [];
            if (0 < self::$wait_time) usleep(self::$wait_time);

            //Collect result
            $result += self::collect();
        }

        unset($job_list, $jobs);
        return $result;
    }

    /**
     * Get php config from "cfg.ini"
     *
     * @return bool
     */
    private static function get_php(): bool
    {
        if ('' !== self::$php_exe) return true;

        if ('' === cli::config) {
            debug(__CLASS__, 'Config file path NOT defined!');
            return false;
        }

        $path = realpath(cli::config);
        if (false === $path) {
            debug(__CLASS__, 'File [' . cli::config . '] NOT found!');
            return false;
        }

        $config = parse_ini_file($path, true);
        if (!is_array($config) || empty($config)) {
            debug(__CLASS__, '[' . cli::config . '] setting incorrect!');
            return false;
        }

        $cmd = $config;
        $keys = false === strpos(self::$php_key, ':') ? [self::$php_key] : explode(':', self::$php_key);
        foreach ($keys as $key) {
            if (!isset($cmd[$key])) {
                debug(__CLASS__, '[' . self::$php_key . '] NOT configured!');
                return false;
            }
            $cmd = $cmd[$key];
        }

        if (!is_string($cmd)) {
            debug(__CLASS__, '[' . cli::config . '] setting incorrect!');
            return false;
        }

        self::$php_exe = '"' . trim($cmd, ' "\'\t\n\r\0\x0B') . '"';
        unset($path, $config, $keys, $key, $cmd);
        return true;
    }

    /**
     * Execute processes
     */
    private static function execute(): void
    {
        //Build command
        $command = self::$php_exe . ' "' . ROOT . '/api.php"';
        if (self::$wait) $command .= ' --record "result"';
        if (0 < self::$process_time) $command .= ' --timeout ' . self::$process_time;

        //Process starts
        foreach (self::$jobs as $key => $item) {
            $cmd = $command . ' --cmd "' . $item['cmd'] . '"';
            if (!empty($item['arg'])) $cmd .= ' --data "' . addcslashes(json_encode($item['arg']), '"') . '"';

            //Create process
            $process = proc_open(os::proc_cmd($cmd), [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, cli::work_path);

            if (is_resource($process)) {
                self::$resource[$key]['exec'] = true;
                self::$resource[$key]['proc'] = $process;
                self::$resource[$key]['pipe'] = $pipes;
            } else {
                debug(__CLASS__, 'Access denied or [' . $item['cmd'] . '] ERROR!');
                self::$resource[$key]['exec'] = false;
            }
        }

        unset($command, $key, $item, $cmd, $process, $pipes);
    }

    /**
     * Collect result
     *
     * @return array
     */
    private static function collect(): array
    {
        $result = [];

        while (!empty(self::$resource)) {
            foreach (self::$resource as $key => $item) {
                if (!$item['exec']) {
                    $result[$key] = ['exec' => false];
                    unset(self::$resource[$key]);
                    continue;
                }

                //Check running status
                if (proc_get_status($item['proc'])['running']) {
                    usleep(0 < self::$process_time ? self::$process_time : 10);
                    continue;
                }

                //build result
                $result[$key] = [];
                $result[$key]['exec'] = true;

                //Read data
                $data = trim(stream_get_contents($item['pipe'][1]));

                //Process data
                if ('' !== $data) {
                    $json = json_decode($data, true);
                    $result[$key]['data'] = !is_null($json) ? $json : $data;
                } else $result[$key]['data'] = '';

                //Close Pipes & process
                foreach ($item['pipe'] as $pipe) fclose($pipe);
                unset(self::$resource[$key]);
                proc_close($item['proc']);
            }
        }

        unset($key, $item, $data, $json, $pipe);
        return $result;
    }
}