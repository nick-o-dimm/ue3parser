<?php

/**
 * Class App
 */
class App
{

    /** @var Ini_File */
    private $iniJobList = null;

    /**
     * App constructor.
     * @param string $jobListFile
     * @param bool $calcElapsedTime
     */
    public function __construct($jobListFile, $calcElapsedTime = true)
    {
        if ($calcElapsedTime) {
            $startTime = microtime(true);
        }

        $this->iniJobList = new Ini_File();
        $this->iniJobList->readFromFile($jobListFile);
        $this->run();

        if ($calcElapsedTime) {
            $elapsedTime = microtime(true) - $startTime;
            echo '<br/>Elapsed time: ' . sprintf("%.6f", $elapsedTime) . ' sec.';
        }
    }

    /**
     * Run all active jobs
     */
    private function run()
    {
        $jobList = $this->iniJobList->getSectionNameList();

        foreach ($jobList as $strJob) {
            if ($strJob === 'default') {
                continue;
            }

            $tmp = explode(':', $strJob);
            $jobName = $tmp[0];
            $jobStatus = $tmp[1] ?? 'OFF';
            $jobStatus = strtoupper($jobStatus);

            $jobClassName = 'Job_' . $jobName;

            if (($jobStatus === 'ON') && class_exists($jobClassName)) {
                /** @var Job_Abstract $jobObj */
                $jobObj = new $jobClassName();

                $defaultContext = $this->iniJobList->getSectionByName('default')->getContext();;
                $jobContext = $this->iniJobList->getSectionByName($strJob)->getContext();
                $defaultContext->addContext($jobContext);

                $jobObj->init($defaultContext);
                $jobObj->run();
            }
        }
    }

}
