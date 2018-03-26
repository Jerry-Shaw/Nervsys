<?php

/**
 * Multi-Process Controller Extension
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

    //Read time (in microseconds)
    public static $read_time = 0;

    //Max running processes
    public static $max_runs = 10;

    //PHP cmd key name in "conf.ini"
    public static $php_key = 'php';

    //PHP executable path in "conf.ini"
    public static $php_exe = '';

    //Basic command
    private static $cmd = '';

    //Process jobs
    private static $jobs = [];

    /**
     * Begin process
     */
    public static function begin(): void
    {
        //Reset jobs
        self::$jobs = [];
    }

    /**
     * Add to process list
     *
     * @param string $cmd
     * @param array  $argv
     * @param string $key
     */
    public static function add(string $cmd, array $argv, string $key = ''): void
    {
        '' === $key ? self::$jobs[] = ['cmd' => &$cmd, 'arg' => &$argv] : self::$jobs[$key] = ['cmd' => &$cmd, 'arg' => &$argv];
        unset($cmd, $argv);
    }

    /**
     * Commit to process
     */
    public static function commit(): array
    {
        //Empty job
        if (empty(self::$jobs)) return [];

        //Check php cmd
        if ('' === self::$php_exe) self::$php_exe = cli::get_cmd(self::$php_key);

        //Split jobs
        $job_pack = count(self::$jobs) < self::$max_runs ? [self::$jobs] : array_chunk(self::$jobs, self::$max_runs, true);

        //Build command
        self::$cmd = self::$php_exe . ' "' . ROOT . '/api.php"';
        if (self::$wait) self::$cmd .= ' --ret';
        if (0 < self::$read_time) self::$cmd .= ' --time ' . self::$read_time;

        $result = [];

        foreach ($job_pack as $jobs) {
            //Copy jobs
            self::$jobs = $jobs;
            //Execute process
            $data = self::execute();
            //Merge result
            if (!empty($data)) $result += $data;
        }

        unset($job_pack, $jobs, $data);
        return $result;
    }

    /**
     * Execute processes
     */
    private static function execute(): array
    {
        //Resource list
        $resource = [];

        //Start process
        foreach (self::$jobs as $key => $item) {
            $cmd = self::$cmd . ' --cmd "' . $item['cmd'] . '"';
            if (!empty($item['arg'])) $cmd .= ' --data "' . addcslashes(json_encode($item['arg']), '"') . '"';

            //Create process
            $process = proc_open(os::cmd_proc($cmd), [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, cli::work_path);

            //Store resource
            if (is_resource($process)) {
                $resource[$key]['exec'] = true;
                $resource[$key]['pipe'] = $pipes;
                $resource[$key]['proc'] = $process;
            } else {
                debug(__CLASS__, 'Access denied or [' . $item['cmd'] . '] ERROR!');
                $resource[$key]['exec'] = false;
            }
        }

        unset($key, $item, $cmd, $pipes);

        //Check wait options
        if (!self::$wait) return [];
        if (0 < self::$wait_time) usleep(self::$wait_time);

        //Collect result
        $result = self::collect($resource);

        unset($resource, $process);
        return $result;
    }

    /**
     * Collect result
     *
     * @param array $resource
     *
     * @return array
     */
    private static function collect(array $resource): array
    {
        $result = [];

        //Collect data
        while (!empty($resource)) {
            foreach ($resource as $key => $item) {
                //Build result
                if (!isset($result[$key])) {
                    $result[$key]['exec'] = $item['exec'];
                    $result[$key]['data'] = '';
                }

                //Unset failed process
                if (!$item['exec']) {
                    //Unset resource
                    unset($resource[$key]);
                    continue;
                }

                //Unset finished process
                if (feof($item['pipe'][1])) {
                    //Close pipes & process
                    foreach ($item['pipe'] as $pipe) fclose($pipe);
                    proc_close($item['proc']);
                    //Unset resource
                    unset($resource[$key]);
                    continue;
                }

                //Read pipe
                $result[$key]['data'] .= trim((string)fgets($item['pipe'][1], 4096));
            }
        }

        //Process data
        foreach ($result as $key => $item) {
            if ('' === $item['data']) continue;

            $json = json_decode($item['data'], true);
            $result[$key]['data'] = !is_null($json) ? $json : $item['data'];
        }

        unset($resource, $key, $item, $pipe, $json);
        return $result;
    }
}