<?php

namespace App\Bridge\Network;

use App\Bridge\Bridge;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Exception\InvalidArgumentException;

/**
 * Wrapper for the UNIX `ip` command.
 */
class IPTools extends Bridge
{
    const LINK_SET_UP       = 'up';
    const LINK_SET_DOWN     = 'down';
    const TUNTAP_MODE_TAP   = 'tap';
    protected $logger;

    public static function getCommand(): string
    {
        return 'sudo';
    }

    /**
     * Add addresses for one interface.
     *
     * @param string $name The device name.
     * @param string $address The address to add. Should be in CIDR notation.
     * @throws Exception If the device name is empty.
     * @throws ProcessFailedException If the process didn't terminate successfully.
     * @return Process The executed process.
     */
    public static function addrAdd(string $name, string $address) : Process {
        if (empty($name)) {
            throw new Exception("Device name cannot be empty.");
        }

        $command = [ 'ip', 'addr', 'add', $address, 'dev', $name ];

        return static::exec($command);
    }

    /**
     * Show informations about addresses for one or all interfaces.
     *
     * @param string $name The device name. If set to `null`, gather informations about all devices.
     * @throws ProcessFailedException If the process didn't terminate successfully.
     * @return Process The executed process.
     */
    public static function addrShow(string $name = null) : Process {
        $command = [ 'ip', 'addr', 'show' ];

        if (!empty($name)) {
            array_push($command, 'dev', $name);
        }

        return static::exec($command);
    }

    /**
     * Delete an interface.
     *
     * @param string $name The device name.
     * @throws Exception If the device name is empty.
     * @throws ProcessFailedException If the process didn't terminate successfully.
     * @return Process The executed process.
     */
    public static function linkDelete(string $name) : Process {
        if (empty($name)) {
            throw new Exception("Device name cannot be empty.");
        }

        $command = [ 'ip', 'link', 'delete', $name ];
        
        return static::exec($command);
    }

    /**
     * Show informations for one or all interfaces.
     *
     * @param string $name The device name. If set to `null`, gather informations about all devices.
     * @throws ProcessFailedException If the process didn't terminate successfully.
     * @return Process The executed process.
     */
    public static function linkShow(string $name = null) : Process {
        $command = [ 'ip', 'link', 'show' ];

        if (!empty($name)) {
            array_push($command, 'dev', $name);
        }

        return static::exec($command);
    }

    /**
     * Set state for one interface.
     *
     * @param string $name The device name.
     * @param string $operand The operand to execute. Bitmask set by a const from this class.
     * @throws Exception If the device name is empty.
     * @throws ProcessFailedException If the process didn't terminate successfully.
     * @return Process The executed process.
     */
    public static function linkSet(string $name, string $operand = self::LINK_SET_UP) : Process {
        if (empty($name)) {
            throw new Exception("Device name cannot be empty.");
        }

        $command = [ 'ip', 'link', 'set', $name, $operand ];
        
        return static::exec($command);
    }

    /**
     * Add a routing table rule.
     *
     * @see https://www.systutorials.com/docs/linux/man/8-ip-route/
     * @param string $route The route to add.
     * @param int $tableId The id of the table to add the route.
     * @throws Exception If the route string is empty.
     * @throws ProcessFailedException If the process didn't terminate successfully.
     * @return Process The executed process.
     */
    public static function routeAdd(string $route, string $gateway) : Process {
        if (empty($route)) {
            throw new Exception("Route cannot be empty.");
        }

        //$route = explode(' ', $route);

        $command = [ 'ip', 'route', 'add' ];
        array_push($command, $route);
        array_push($command, 'via');
        array_push($command,$gateway);

        return static::exec($command);
    }

    /**
     * Show existing routes.
     *
     * @see https://www.systutorials.com/docs/linux/man/8-ip-route/
     * @param int $tableId The id of the table to show.
     * @throws ProcessFailedException If the process didn't terminate successfully.
     * @return Process The executed process.
     */
    public static function routeShow(int $tableId = 254) : Process {
        $command = [ 'ip', 'route', 'show', 'table', (string) $tableId ];
        
        return static::exec($command);
    }

    /**
     * Delete a routing table rule.
     *
     * @see https://www.systutorials.com/docs/linux/man/8-ip-route/
     * @param string $route The route to delete.
     * @param int $tableId The id of the table to delete the route.
     * @throws Exception If the route string is empty.
     * @throws ProcessFailedException If the process didn't terminate successfully.
     * @return Process The executed process.
     */
    public static function routeDelete(string $route, string $gateway=null){
        if (empty($route)) {
            throw new Exception("Route cannot be empty.");
        }     

        $command = [ 'ip', 'route', 'del' ];
        array_push($command, $route);
        if (!is_null($gateway)) {
            array_push($command, 'via');
            array_push($command,$gateway);
        }
            return static::exec($command);
                throw new Exception("Route delete in error");
    }

    /**
     * Add a routing policy rule.
     *
     * @see http://man7.org/linux/man-pages/man8/ip-rule.8.html
     * @param string $selector The selector to apply.
     * @param string $action The action to add.
     * @throws Exception If the selector or device is empty.
     * @throws ProcessFailedException If the process didn't terminate successfully.
     * @return Process The executed process.
     */
    public static function ruleAdd(string $selector, string $action) : Process {
        if (empty($selector) || empty($action)) {
            throw new Exception("Selector or action cannot be empty.");
        }

        $selector = explode(' ', $selector);
        $action = explode(' ', $action);

        $command = [ 'ip', 'rule', 'add' ];
        array_push($command, ...$selector);
        array_push($command, ...$action);

        return static::exec($command);
    }

    /**
     * Delete a routing policy rule.
     *
     * @see http://man7.org/linux/man-pages/man8/ip-rule.8.html
     * @param string $selector The selector to apply.
     * @param string $action The action to delete.
     * @throws Exception If the selector or device is empty.
     * @throws ProcessFailedException If the process didn't terminate successfully.
     * @return Process The executed process.
     */
    public static function ruleDelete(string $selector, string $action) : Process {
        if (empty($selector) || empty($action)) {
            throw new Exception("Selector or action cannot be empty.");
        }

        $selector = explode(' ', $selector);
        $action = explode(' ', $action);

        $command = [ 'rule', 'del' ];
        array_push($command, ...$selector);
        array_push($command, ...$action);

        return static::exec($command);
    }

    public static function ruleExists(string $selector, string $action) : bool
    {
        if (empty($selector) || empty($action)) {
            throw new Exception("Selector or action cannot be empty.");
        }
        
        $rule = $selector . ' ' . $action;

        $selector = explode(' ', $selector);
        $action = explode(' ', $action);

        $command = [ 'rule', 'show' ];
        array_push($command, ...$selector);
        array_push($command, ...$action);

        $output = static::exec($command);

        $rule = preg_quote($rule, '/');
        $rule = preg_replace('/$rule/', '\/', $rule);

        if ($output->getExitCode() == 0 && preg_match('/(' . $rule . ')/', $output->getOutput())) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Add a TUN:TAP interface.
     *
     * @param string $name The device name.
     * @param string $mode The mode for the interface. Bitmask set by a const from this class.
     * @throws Exception If the device name is empty.
     * @throws ProcessFailedException If the process didn't terminate successfully.
     * @return Process The executed process.
     */
    public static function tuntapAdd(string $name, string $mode = self::TUNTAP_MODE_TAP) : Process {
        if (empty($name)) {
            throw new Exception("Device name cannot be empty.");
        }

        $command = [ 'tuntap', 'add', $name, 'mode', $mode ];

        return static::exec($command);
    }

    public static function routeExists(string $route, int $tableId = 254) : bool
    {
        $output = static::routeShow($tableId);
        $route = preg_quote($route, '/');
        $exists = preg_match('/(' . $route . ')/', $output->getOutput());

        if($exists) {
            return true;
        } else {
            return false;
        }
    }
    
    
    public static function networkInterfaceExists(string $name) : bool {
        try {
            $output = static::linkShow($name);
        } catch (ProcessFailedException $exception) {
            return false;
        }

        return true;
    }

    /**
     * Check if a given IP is set to an interface.
     *
     * @param string $name The device name.
     * @param string $IP The IP to check. IP must be of form X.X.X.X/M .
     * @throws Exception If the device name or IP is empty.
     * @throws ProcessFailedException If the process didn't terminate successfully.
     * @return Process The executed process.
     */
    public static function networkIPExists(string $name,string $ip) : bool {
        if (empty($name)) {
            throw new InvalidArgumentException("networkIPExists - Device name cannot be empty.");
        }
        if (empty($ip)) {
            throw new InvalidArgumentException("networkIPExists - IP field cannot be empty.");
        }

        $command = [ 'addr', 'show', 'dev', $name ];
        $output=static::exec($command);
        $pattern=preg_quote("inet ".$ip, '/');
        if (preg_match('/'.$pattern.'/',$output->getOutput()))
            return true;
        else
            return false;

    }

    /**
     * Add a network in Kea DHCP
     * @param string $host hostname of the Kea DHCP agent
     * @param integer $port port of the Kea DHCP agent
     * @param string $filename Kea DHCP configuration filename. Must be absolute path
     * @param network  $address The address to add. Should be in CIDR notation.
     * @throws Exception If the device name is empty.
     * @throws ProcessFailedException If the process didn't terminate successfully.
     * @return Process The executed process.
     */
    public static function NetworkIfExistDHCP(string $host, $port, string $filename, $address) : bool {
        $fileContent = file_get_contents($filename);
        $tab = json_decode($fileContent, true);
        $result=false;
        $i=0;
        while ($result) {
            if ($tab['Dhcp4']['subnet4']==$address->__toString())
                $result=true;
        }
        return $result;
    }

    /**
     * Add a network in Kea DHCP
     * @param string $host hostname of the Kea DHCP agent
     * @param integer $port port of the Kea DHCP agent
     * @param Network $address The address to add. Should be in CIDR notation.
     * @param IP $minIP The first IP of the pool.
     * @param IP $maxIP The second IP of the pool.
     * @throws Exception If the device name is empty.
     * @throws ProcessFailedException If the process didn't terminate successfully.
     * @return Process The executed process.
     */
    public static function addnetworkDHCP(string $host, $port, string $filename, $address, $minIP, $maxIP) {
        try {
            $fileContent = file_get_contents($filename);
        }
            catch(ErrorException $e) {
                throw new Exception("Error opening file");
        }
// TODO : test if the json_decode return NULL
// The Kea DHCP accept comment with // but it's not json valide
        $tab = json_decode($fileContent, true);

        if (!static::NetworkIfExistDHCP($host, $port, $filename, $address)) {
            $idMAX = 1;
            //looking for the last ID to generate a new ID for the new pool

            for ($i = 0; $i < count($tab['Dhcp4']['subnet4']); $i++){
                if ($tab['Dhcp4']['subnet4'][$i]['id'] > $idMAX){
                    $idMAX = $tab['Dhcp4']['subnet4'][$i]['id'];
                }
            }
            #on incrémente l'ID max de 1 afin d'attribuer à notre nouveau subnet
            #un ID qui n'est pas encore utilisé
            $idMAX++;
            print "\n##### Subnet absent, ajout du subnet puis du pool. #####\n";
            #ajout du subnet dans le tableau subnet4
            $subnet[] = array("subnet" => $address->__toString(), "id" => $idMAX);
            $tab['Dhcp4']['subnet4'] = array_merge($tab['Dhcp4']['subnet4'], $subnet);
            #création du pool
            $firstIP=$minIP->getAddr();
            $lastIP=$maxIP->getAddr();
            $pool[] = array("pool" => "$firstIP - $lastIP");
            #création du tableau pools pour ajout
            $pools = array("pools" => $pool);
            #on détermine l'indice du subnet ajouté
            for ($i = 0; $i < count($tab['Dhcp4']['subnet4']); $i++){
                if($tab['Dhcp4']['subnet4'][$i]['subnet'] == $address->__toString()){
                    #on ajoute le tableau pools
                    $tab['Dhcp4']['subnet4'][$i] = array_merge($tab['Dhcp4']['subnet4'][$i], $pools);
                }
            }
        }
        $json = array(
            "command" => "config-set",
            "service" => [ "dhcp4" ],
            "arguments" => $tab
        );

        $contenu = json_encode($json);

        #on envoie le json à l'agent KEA
        $options = array(
            'http' => array(
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n" . "Accept: application/json\r\n",
                'content' => $contenu
            )
        );

        $url = "http://$host:$port";
        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        $response = json_decode($result);

        #on met le json des arguments de la commande write sous forme de tab php
        $arguments = '{"filename": '.$filename.'}';
        $arguments = json_decode($arguments, true);

        $json = array(
            "command" => "config-write",
            "service" => [ "dhcp4" ],
            "arguments" => $tab
        );

        $contenu = json_encode($json);

        #on envoie le json à l'agent KEA
        $options = array(
            'http' => array(
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n" . "Accept: application/json\r\n",
                'content' => $contenu
            )
        );

        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        $response = json_decode($result);

        //return true;
    
    }

}