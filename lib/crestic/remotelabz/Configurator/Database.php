<?php

namespace RemoteLabz\Configurator;

use RemoteLabz\Logger;
use RemoteLabz\Configurator\Configurator;
use RemoteLabz\Exception\ConfigurationException;

/**
 * Handle database configuration.
 * 
 * @author Julien Hubert <julien.hubert1@univ-reims.fr>
 */
class Database
{
    /** @var bool $doMigration  Wether to execute database migrations or not. */
    private $doMigration;

    /** @var bool $doFixtures   Wether to load database fixtures or not. */
    private $doFixtures;    
    
    public function __construct($doMigration = true, $doFixtures = true) {
        $this->doMigration = $doMigration;
        $this->doFixtures = $doFixtures;
    }

    /**
     * Get the value of doMigration
     */ 
    public function getDoMigration()
    {
        return $this->doMigration;
    }

    /**
     * Set the value of doMigration
     *
     * @return  self
     */ 
    public function setDoMigration($doMigration)
    {
        $this->doMigration = $doMigration;

        return $this;
    }

    /**
     * Get the value of doFixtures
     */ 
    public function getDoFixtures()
    {
        return $this->doFixtures;
    }

    /**
     * Set the value of doFixtures
     *
     * @return  self
     */ 
    public function setDoFixtures($doFixtures)
    {
        $this->doFixtures = $doFixtures;

        return $this;
    }

    static public function configure(array $options, array $operands, Logger $log)
    {
        $doMigration = !$options['skip-migration'];
        $doFixtures = !$options['skip-fixtures'];

        if ($doMigration) {
            $log->debug("Starting database migration");
            $output = array();
            $return = 0;
            exec("php " . dirname(__FILE__) . "/../../../../bin/console doctrine:migrations:migrate -n 2>&1", $output, $return);
            $log->debug($output);
            if ($return !== 0) {
                throw new ConfigurationException("Error while migrating database.");
            }
        }

        if ($doFixtures) {
            $log->debug("Starting database fixtures load");
            $output = array();
            $return = 0;
            exec("php " . dirname(__FILE__) . "/../../../../bin/console doctrine:fixtures:load -n 2>&1", $output, $return);
            $log->debug($output);
            if ($return !== 0) {
                throw new ConfigurationException("Error while loading databases fixtures.");
            }
        }
    }
}