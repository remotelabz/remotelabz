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

            exec("php " . dirname(__FILE__) . "/../../../bin/console doctrine:schema:drop --force --full-database 2>&1", $output, $return);
            
            $log->debug("Starting database migration");
            Logger::println('Starting database migration', Logger::COLOR_NONE, 1);
            exec("php " . dirname(__FILE__) . "/../../../bin/console doctrine:migrations:migrate -n 2>&1", $output, $return);
            Logger::println($output, null, 1);
            $log->debug($output);
            if ($return !== 0) {
                throw new ConfigurationException("Error while migrating database.");
            }
            Logger::println('Ended database migration', Logger::COLOR_NONE, 1);
        }

        if ($doFixtures) {
            $log->debug("Starting database fixtures loading");

            $log->debug("Clear cache before database fixtures loading");
            Logger::println('Clear cache before database fixtures loading', Logger::COLOR_NONE, 1);

            exec("php " . dirname(__FILE__) . "/../../../bin/console cache:clear -n 2>&1", $output, $return);
            Logger::println($output, null, 1);
            $log->debug($output);
            if ($return !== 0) {
                throw new ConfigurationException("Error while clear cache.");
            }

            Logger::println('Starting database fixtures loading', Logger::COLOR_NONE, 1);
            
            /*
            
            $log->debug("First - Drop the database");
            Logger::println('First - Drop the database', Logger::COLOR_NONE, 1);
            
            exec("php " . dirname(__FILE__) . "/../../../bin/console doctrine:schema:drop --force 2>&1", $output, $return);
            Logger::println($output, null, 1);
            $log->debug($output);
            if ($return !== 0) {
                throw new ConfigurationException("Error while drop the databases.");
            }
                
            $log->debug("Second - Create the schema");
            Logger::println('Second - Create the schema', Logger::COLOR_NONE, 1);

            exec("php " . dirname(__FILE__) . "/../../../bin/console doctrine:schema:create 2>&1", $output, $return);
            Logger::println($output, null, 1);
            $log->debug($output);
            if ($return !== 0) {
                throw new ConfigurationException("Error while loading databases fixtures.");
            }

            $log->debug("Final - Load the data");
            Logger::println('Final - Load the data', Logger::COLOR_NONE, 1);
            */
            
            exec("php " . dirname(__FILE__) . "/../../../bin/console doctrine:fixtures:load -n 2>&1", $output, $return);
            Logger::println($output, null, 1);
            $log->debug($output);
            if ($return !== 0) {
                throw new ConfigurationException("Error while loading databases fixtures.");
            }
            Logger::println('Ended database fixtures loading', Logger::COLOR_NONE, 1);
        }
    }
}