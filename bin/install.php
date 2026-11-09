<?php

/* =====================================================================
   Numéros du menu d'install.sh (d'après le log d'installation)
   ===================================================================== */
const MENU_REQUIREMENTS = '1';
const MENU_OPENVPN      = '2';
const MENU_SSL          = '3';
const MENU_REMOTELABZ   = '4';
const MENU_WORKERS      = '5';
const MENU_FULL_AUTO    = '7';
const MENU_EXIT         = '8';

// Réponse envoyée à "Deployment topology (1=single-server, 2=multi-server)"
const TOPOLOGY_ANSWERS = [
    'single_server' => '1',
    'multiserver'   => '2',
];

const MAX_WORKERS = 20;

/* =====================================================================
   Questions posées par install.sh : [expression sur la ligne en cours, clé].
   Les prompts (read -p) n'ont pas de retour à la ligne : on les reconnaît
   donc sur le début de ligne affiché, avant que l'utilisateur ne réponde.
   Une réponse vide envoie simplement Entrée (valeur par défaut du script).
   ===================================================================== */
const PROMPTS = [
    ['/^Enter your choice \[[^\]]*\]:\s*$/i',                          'menu'],
    ['/review\/edit the existing configuration\?.*:\s*$/i',            'review'],
    ['/^Is your certificate self-signed\?.*:\s*$/i',                   'self_signed'],
    ['/^Do you want to deploy RemoteLabz workers\?.*:\s*$/i',          'deploy'],
    ['/^How many workers\?.*:\s*$/i',                                  'count'],
    ['/^Worker user account \[.*\]:\s*$/i',                            'w_user'],
    ['/^Deployment topology .*:\s*$/i',                                'w_topology'],
    ['/^Worker IP address:\s*$/i',                                     'w_ip'],
    ['/^(Worker )?administration interface \[.*\]:\s*$/i',             'w_admin_if'],
    ['/^(Worker )?data interface \(or Enter.*:\s*$/i',                 'w_data_if'],
    ['/^(Worker )?internet access interface \(or Enter.*:\s*$/i',      'w_internet_if'],
    ['/^Front server IP \[.*\]:\s*$/i',                                'w_front_ip'],
    ['/^Data network \[.*\]:\s*$/i',                                   'w_data_network'],
    ['/^Internal data IP \[.*\]:\s*$/i',                               'w_internal_ip'],
    ['/^Data network gateway \[.*\]:\s*$/i',                           'w_gateway'],
    ['/^Is the VPN hosted on this worker\?.*:\s*$/i',                  'w_vpn'],
    ['/^Password for user \'.*\' on worker \[.*\]:\s*$/i',             'w_user_password'],
    ['/^Password for remotelabz-worker user \[.*\]:\s*$/i',            'w_worker_password'],
    ['/^Password for remotelabz \(front\) user \[.*\]:\s*$/i',         'w_front_password'],
    ['/^Enable WSS \(Secure WebSocket\)\?.*:\s*$/i',                   'w_wss'],
    ['/^Contact email \[.*\]:\s*$/i',                                  'contact_email'],
    ['/^Password for user remotelabz-worker \[.*\]:\s*$/i',            'ssh_password'],
    ['/(press|appuyez).*(enter|entr)/i',                               'enter'],
];

// Clés dont la réponse est un mot de passe (masquée dans le terminal affiché)
const SECRET_PROMPTS = ['w_user_password', 'w_worker_password', 'w_front_password', 'ssh_password'];


/* ===================== Fonctions utilitaires ===================== */

function fail($msg){
    http_response_code(400);
    echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Erreur</title></head>'
       . '<body><pre>' . htmlspecialchars($msg) . '</pre></body></html>';
    exit;
}

// Lit un champ texte d'un tableau (retour à la ligne interdit : la valeur est
// envoyée sur stdin d'install.sh ou écrite dans .env.local).
function field($arr, $key, $label, $required = false){
    $v = $arr[$key] ?? '';
    if(!is_string($v) || preg_match('/[\r\n\0]/', $v)){
        fail("$label : valeur invalide.");
    }
    if($required && $v === ''){
        fail("$label : champ obligatoire.");
    }
    return $v;
}

function post($key){
    if(!isset($_POST[$key])){
        fail("Champ manquant : $key");
    }
    return field($_POST, $key, $key, true);
}

// Champ POST facultatif ('' si absent)
function optPost($key){
    return field($_POST, $key, $key);
}

// Modifie une variable existante de .env.local sans passer par le shell
function editEnv($key, $newVal){
    if(preg_match('/[\r\n\0]/', $newVal)){
        fail("Valeur invalide pour $key");
    }
    $file = '/opt/remotelabz/.env.local';
    $content = file_get_contents($file);
    if($content === false){
        return;
    }
    $line = $key . '=' . $newVal;
    $content = preg_replace_callback(
        '/^' . preg_quote($key, '/') . '=.*$/m',
        function () use ($line) { return $line; },
        $content
    );
    file_put_contents($file, $content, LOCK_EX);
}

// Lit et valide les workers envoyés par le formulaire
function readWorkers($prefix, $countKey){
    $count = (int) post($countKey);
    if($count < 1 || $count > MAX_WORKERS){
        fail("Nombre de workers invalide (1 à " . MAX_WORKERS . ").");
    }
    $list = $_POST[$prefix] ?? null;
    if(!is_array($list)){
        fail("Informations des workers manquantes.");
    }

    $workers = [];
    for($i = 0; $i < $count; $i++){
        $n = $i + 1;
        $w = $list[$i] ?? null;
        if(!is_array($w)){
            fail("Worker $n : informations manquantes.");
        }

        $row = [
            'user'     => field($w, 'user',     "Worker $n : compte utilisateur", true),
            'topology' => field($w, 'topology', "Worker $n : topologie", true),
            'vpn'      => field($w, 'vpn',      "Worker $n : VPN", true),
            'wss'      => field($w, 'wss',      "Worker $n : WSS") ?: 'yes',
        ];
        if(!isset(TOPOLOGY_ANSWERS[$row['topology']])){
            fail("Worker $n : topologie invalide.");
        }
        if(!in_array($row['vpn'], ['yes', 'no'], true) || !in_array($row['wss'], ['yes', 'no'], true)){
            fail("Worker $n : valeur oui/non invalide.");
        }

        // Champs facultatifs : vide = Entrée (valeur par défaut d'install.sh)
        foreach(['worker_password', 'front_password', 'worker_user_password'] as $f){
            $row[$f] = field($w, $f, "Worker $n : $f");
        }
        foreach(['admin_if', 'data_if', 'internet_if'] as $f){
            $row[$f] = field($w, $f, "Worker $n : $f");
            if($row[$f] !== '' && !preg_match('/^[A-Za-z0-9_.:@-]+$/', $row[$f])){
                fail("Worker $n : nom d'interface invalide ($f).");
            }
        }
        foreach(['worker_ip', 'front_ip', 'gateway'] as $f){
            $row[$f] = field($w, $f, "Worker $n : $f");
            if($row[$f] !== '' && !filter_var($row[$f], FILTER_VALIDATE_IP)){
                fail("Worker $n : adresse IP invalide ($f).");
            }
        }
        foreach(['data_network', 'internal_ip'] as $f){
            $row[$f] = field($w, $f, "Worker $n : $f");
            if($row[$f] !== '' && !preg_match('/^[0-9a-fA-F:.\/]+$/', $row[$f])){
                fail("Worker $n : réseau invalide ($f).");
            }
        }

        if($row['topology'] === 'multiserver'){
            if($row['worker_ip'] === ''){
                fail("Worker $n : l'IP du worker est obligatoire en multiserveur.");
            }
            if($row['admin_if'] === ''){
                fail("Worker $n : l'interface d'administration est obligatoire en multiserveur.");
            }
        }
        $workers[] = $row;
    }
    return $workers;
}

// Calcule la réponse (sans retour à la ligne) à un prompt reconnu
function promptAnswer($key, &$ctx){
    $w = null;
    if(strpos($key, 'w_') === 0){
        if($key === 'w_user'){
            $ctx['index']++;
        }
        $w = $ctx['workers'][$ctx['index']] ?? null;
        if($w === null){
            return '';
        }
    }

    switch($key){
        case 'menu':
            $ctx['menus']++;
            return $ctx['menus'] === 1 ? $ctx['menu'] : MENU_EXIT;
        case 'review':          return 'n';
        case 'self_signed':     return $ctx['self_signed'];
        case 'deploy':          return count($ctx['workers']) > 0 ? 'y' : 'n';
        case 'count':           return (string) count($ctx['workers']);
        case 'contact_email':   return $ctx['contact_email'];
        case 'ssh_password':    return $ctx['ssh_password'];
        case 'enter':           return '';

        case 'w_user':          return $w['user'];
        case 'w_topology':      return TOPOLOGY_ANSWERS[$w['topology']];
        case 'w_ip':            return $w['worker_ip'];
        case 'w_admin_if':      return $w['admin_if'];
        case 'w_data_if':       return $w['data_if'];
        case 'w_internet_if':   return $w['internet_if'];
        case 'w_front_ip':      return $w['front_ip'] !== '' ? $w['front_ip'] : $ctx['front_ip'];
        case 'w_data_network':  return $w['data_network'];
        case 'w_internal_ip':   return $w['internal_ip'];
        case 'w_gateway':       return $w['gateway'];
        case 'w_vpn':           return $w['vpn'] === 'yes' ? 'y' : 'n';
        case 'w_wss':           return $w['wss'] === 'yes' ? 'y' : 'n';
        case 'w_user_password':   return $w['worker_user_password'];
        case 'w_worker_password': return $w['worker_password'];
        case 'w_front_password':  return $w['front_password'];
    }
    return '';
}

// Envoie une commande JS à la carte d'étape de la page
function ui($options){
    echo '<script>InstallUI.setStep('
        . json_encode($options, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE)
        . ')</script>';
}

// Masque la réponse (écho du terminal) qui suit un mot de passe
function maskEcho($text, &$mask){
    if(!$mask){
        return $text;
    }
    $pos = strpos($text, "\n");
    if($pos === false){
        return str_repeat('*', strlen($text));
    }
    $mask = false;
    return str_repeat('*', $pos) . substr($text, $pos);
}

// Lit la sortie d'install.sh en continu, l'affiche, et répond aux prompts.
// Retourne le code de sortie.
function streamInstall($process, $pipes, &$ctx){
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    $pending   = '';     // ligne en cours (sans retour à la ligne final)
    $mask      = false;
    $finishing = false;
    $lastData  = time();
    $warned    = '';

    while(true){
        $read = [$pipes[1], $pipes[2]];
        $w = $e = null;
        @stream_select($read, $w, $e, 1);

        $text = '';
        foreach($read as $r){
            $chunk = fread($r, 8192);
            if($chunk !== false && $chunk !== ''){
                $text .= $chunk;
            }
        }

        if($text === ''){
            if($finishing){
                break;
            }
            $st = proc_get_status($process);
            if(!$st['running']){
                $finishing = true;     // dernier passage pour vider les tubes
                continue;
            }
            // Prompt non reconnu ? On le signale dans le terminal.
            if($pending !== '' && time() - $lastData > 20 && $warned !== $pending
               && preg_match('/[:?\]\)]\s*$/', $pending)){
                echo htmlspecialchars("\n[install.php] En attente sur : « " . trim($pending) . " » (prompt non reconnu ?)\n", ENT_SUBSTITUTE);
                flush();
                $warned = $pending;
            }
            continue;
        }
        $lastData = time();

        // Nettoyage : séquences ANSI / OSC, fins de ligne
        $text = preg_replace('/\x1b\][^\x07\x1b]*(\x07|\x1b\\\\)/', '', $text);
        $text = preg_replace('/\x1b\[[0-9;?]*[A-Za-z]/', '', $text);
        $text = str_replace("\r\n", "\n", $text);

        // Lignes complètes -> étapes / description ; reste = ligne en cours
        $pending .= $text;
        $lines = explode("\n", $pending);
        $pending = array_pop($lines);
        foreach($lines as $line){
            $line = trim(substr(strrchr("\r" . $line, "\r"), 1));
            if(preg_match('/^STEP (\d+): (.+)$/', $line, $m)){
                ui(['number' => 'Étape ' . $m[1] . '/6', 'title' => $m[2],
                    'progress' => ((int) $m[1] - 1) / 6 * 100]);
            }elseif($line === 'Worker Deployment Configuration'){
                ui(['title' => 'Configuring Worker Deployments']);
            }elseif($line === 'Deploying Workers with Ansible'){
                ui(['title' => 'Deploying Workers']);
            }elseif(preg_match('/^🔥\s*(.+)$/u', $line, $m) && strpos($m[1], '───') === false){
                ui(['description' => $m[1]]);
            }
        }
        $pending = substr(strrchr("\r" . $pending, "\r"), 1);   // après le dernier \r

        // Affichage (sans \r, mots de passe masqués)
        echo htmlspecialchars(maskEcho(str_replace("\r", '', $text), $mask), ENT_SUBSTITUTE);
        flush();

        // Prompt reconnu ? -> réponse
        foreach(PROMPTS as $p){
            if(preg_match($p[0], $pending)){
                $answer = promptAnswer($p[1], $ctx);
                fwrite($pipes[0], $answer . "\n");
                if(in_array($p[1], SECRET_PROMPTS, true)){
                    $mask = true;
                }
                $pending = '';
                break;
            }
        }
    }

    fclose($pipes[0]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return proc_close($process);
}


/* ============================ Traitement ============================ */

if($_SERVER['REQUEST_METHOD'] == 'POST'){

set_time_limit(0);
ignore_user_abort(true);   // l'installation continue même si la page est fermée

while (ob_get_level() > 0) { ob_end_flush(); }
@ini_set('zlib.output_compression', '0');
@ini_set('output_buffering', 'off');


    shell_exec("mkdir /opt/remotelabz");

    $installType = post("install_type");
    $continueLink = "/";

    // Réponses utilisées par streamInstall()
    $ctx = [
        'menu'          => '',
        'menus'         => 0,
        'self_signed'   => '',
        'contact_email' => '',
        'ssh_password'  => '',
        'front_ip'      => '',
        'workers'       => [],
        'index'         => -1,
    ];

    // Prépare .env.local s'il n'existe pas encore
    $prepareEnv = function () {
        if(!file_exists("/opt/remotelabz/.env.local")){
            shell_exec("cp ../.env /opt/remotelabz/.env.local");
        }
    };

    switch($installType){
        case 'auto_install':
            $prepareEnv();

            editEnv("PUBLIC_ADDRESS", post("ip_public"));
            if(post('server_mode') === "single_server"){
                editEnv("DEPLOY_SINGLE_SERVER", "1");
                editEnv("IP_ADDRESS","127.0.0.1");
            }else{
                editEnv("DEPLOY_SINGLE_SERVER", "0");
                editEnv("IP_ADDRESS", post("ip_public"));
            }
            editEnv("MYSQL_PASSWORD", post("mysql_password"));
            editEnv("SSL_CA_KEY_PASSPHRASE", post("ssl_ca_key_password"));
            editEnv("CONTACT_MAIL", post("contact_email"));

            $ctx['menu']          = MENU_FULL_AUTO;
            $ctx['self_signed']   = (post("self_signed") === "yes") ? "y" : "n";
            $ctx['contact_email'] = post("contact_email");
            $ctx['front_ip']      = post("ip_public");
            if(post("deploy_workers") === "yes"){
                $ctx['workers']      = readWorkers("auto_workers", "auto_workers_count");
                $ctx['ssh_password'] = optPost("auto_ssh_password");
            }
            $continueLink = "https://".post("ip_public");
            break;

        case 'requirements':
            $ctx['menu'] = MENU_REQUIREMENTS;
            break;

        case 'openvpn':
            $prepareEnv();
            editEnv("SSL_CA_KEY_PASSPHRASE", post("openvpn_ssl_ca_key_password"));
            $ctx['menu'] = MENU_OPENVPN;
            break;

        case 'ssl':
            $prepareEnv();
            editEnv("PUBLIC_ADDRESS", post("ssl_ip_public"));
            if(post('ssl_server_mode') === "single_server"){
                editEnv("DEPLOY_SINGLE_SERVER", "1");
                editEnv("IP_ADDRESS","127.0.0.1");
            }else{
                editEnv("DEPLOY_SINGLE_SERVER", "0");
                editEnv("IP_ADDRESS", post("ssl_ip_public"));
            }
            editEnv("MYSQL_PASSWORD", post("ssl_mysql_password"));
            editEnv("SSL_CA_KEY_PASSPHRASE", post("ssl_ssl_ca_key_password"));
            editEnv("CONTACT_MAIL", post("ssl_contact_email"));

            $ctx['menu']          = MENU_SSL;
            $ctx['self_signed']   = (post("ssl_self_signed") === "yes") ? "y" : "n";
            $ctx['contact_email'] = post("ssl_contact_email");
            break;

        case 'remotelabz':
            $prepareEnv();
            editEnv("PUBLIC_ADDRESS", post("rl_ip_public"));
            if(post('rl_server_mode') === "single_server"){
                editEnv("DEPLOY_SINGLE_SERVER", "1");
                editEnv("IP_ADDRESS","127.0.0.1");
            }else{
                editEnv("DEPLOY_SINGLE_SERVER", "0");
                editEnv("IP_ADDRESS", post("rl_ip_public"));
            }
            editEnv("MYSQL_PASSWORD", post("rl_mysql_password"));
            editEnv("SSL_CA_KEY_PASSPHRASE", post("rl_ssl_ca_key_password"));
            editEnv("CONTACT_MAIL", post("rl_contact_email"));

            $ctx['menu']          = MENU_REMOTELABZ;
            $ctx['self_signed']   = (post("rl_self_signed") === "yes") ? "y" : "n";
            $ctx['contact_email'] = post("rl_contact_email");
            $continueLink = "https://".post("rl_ip_public");
            break;

        case 'workers':
            $prepareEnv();
            $ctx['menu']          = MENU_WORKERS;
            $ctx['contact_email'] = post("workers_contact_email");
            $ctx['front_ip']      = optPost("workers_front_ip");
            $ctx['ssh_password']  = optPost("workers_ssh_password");
            $ctx['workers']       = readWorkers("workers", "workers_count");
            break;

        case 'custom':
            fail("Le mode Custom n'est pas encore pris en charge.");
        default:
            die();
    }


    $cmd = "script -q -c './install.sh' /tmp/installLogs.log";

    $descriptorspec = array(
    0 => array("pipe", "r"),   // stdin is a pipe that the child will read from
    1 => array("pipe", "w"),   // stdout is a pipe that the child will write to
    2 => array("pipe", "w")    // stderr is a pipe that the child will write to
    );
    flush();

    $process = proc_open($cmd, $descriptorspec, $pipes, realpath('./'),
                        array(
                            'TERM'    => 'xterm-256color',
                            'SUDO_USER' => get_current_user(),
                            'HOME' => '/home/'.get_current_user(),
                            'PATH' => getenv("PATH")
                            ));


    if (!is_resource($process)) {
        http_response_code(500);
        echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Erreur</title></head>'
        . '<body><pre>Erreur : impossible de démarrer le processus d\'installation.</pre></body></html>';
        exit;
    }

?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>RemoteLabz – Installation en cours...</title>
<style>
    /* Même palette que la page du formulaire (installer.html) */
    :root {
        --bg: #f4f6fa;
        --surface: #ffffff;
        --text: #212529;
        --muted: #6c757d;
        --border: #dee2e6;
        --nav: #1f2a3c;
        --primary: #2f6fdb;
        --primary-soft: #e8f0fd;
        --success: #198754;
        --success-soft: #e6f4ec;
        --danger: #dc3545;
        --danger-soft: #fbe9eb;
        --warning: #b7791f;
        --warning-soft: #fff6e0;
        --radius: 6px;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
        font-family: -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        background: var(--bg);
        color: var(--text);
        font-size: 15px;
        line-height: 1.5;
        min-height: 100vh;
        display: flex;
        flex-direction: column;
    }

    /* ===== Barre de navigation ===== */
    .navbar {
        background: var(--nav);
        color: #fff;
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 0 24px;
        height: 56px;
    }
    .navbar img { height: 28px; display: block; }
    .navbar .brand { font-weight: 600; font-size: 18px; }
    .navbar .spacer { flex: 1; }

    /* ===== En-tête ===== */
    .jumbotron {
        background: var(--surface);
        border-bottom: 1px solid var(--border);
        padding: 28px 24px;
    }
    .jumbotron .inner { max-width: 960px; margin: 0 auto; }
    .jumbotron h1 { font-size: 30px; font-weight: 300; }
    .jumbotron p { color: var(--muted); margin-top: 4px; }

    main { flex: 1; width: 100%; max-width: 960px; margin: 0 auto; padding: 28px 24px; }

    /* ===== Bandeau d'avertissement ===== */
    .warning-banner {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        margin-bottom: 20px;
        padding: 14px 16px;
        background: var(--warning-soft);
        border-left: 4px solid var(--warning);
        border-radius: var(--radius);
        color: var(--text);
        font-size: 14px;
    }
    .warning-banner .icon { font-size: 20px; line-height: 1.2; flex-shrink: 0; }

    /* ===== Cartes ===== */
    .card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        margin-bottom: 20px;
    }

    /* ===== Carte d'étape ===== */
    .step-card { text-align: center; padding: 32px 20px; }

    .step-spinner {
        width: 48px;
        height: 48px;
        margin: 0 auto 18px;
        border: 4px solid var(--primary-soft);
        border-top-color: var(--primary);
        border-radius: 50%;
        animation: spin 0.9s linear infinite;
    }
    .step-card.done .step-spinner {
        animation: none;
        border: none;
        width: auto;
        height: auto;
        font-size: 48px;
        line-height: 1;
        color: var(--success);
    }
    .step-card.done.failed .step-spinner { color: var(--danger); }

    .step-number {
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 2px;
        text-transform: uppercase;
        color: var(--primary);
        margin-bottom: 6px;
    }
    .step-title { font-size: 22px; font-weight: 600; margin-bottom: 8px; }
    .step-description {
        font-size: 14px;
        color: var(--muted);
        line-height: 1.5;
        word-break: break-word;
    }

    .progress-bar {
        margin: 22px auto 0;
        max-width: 420px;
        height: 6px;
        background: var(--primary-soft);
        border-radius: 3px;
        overflow: hidden;
    }
    .progress-fill {
        height: 100%;
        width: 0%;
        background: var(--primary);
        border-radius: 3px;
        transition: width 0.4s ease;
    }
    .progress-label { margin-top: 8px; font-size: 12px; color: var(--muted); }

    @keyframes spin { to { transform: rotate(360deg); } }

    /* ===== Terminal repliable ===== */
    .terminal-toggle {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        width: 100%;
        padding: 11px;
        font: inherit;
        font-weight: 500;
        color: var(--text);
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        cursor: pointer;
    }
    .terminal-toggle:hover { border-color: var(--primary); }
    .terminal-toggle:focus-visible { outline: 2px solid var(--primary); outline-offset: 2px; }
    .terminal-toggle .arrow { transition: transform 0.3s ease; }
    .terminal-wrapper.open .terminal-toggle .arrow { transform: rotate(180deg); }

    /* Replié par défaut */
    .terminal-content {
        display: none;
        flex-direction: column;
        margin-top: 10px;
        background: #1e1e2e;
        border: 1px solid var(--border);
        border-radius: var(--radius);
        overflow: hidden;
    }
    .terminal-wrapper.open .terminal-content { display: flex; }

    .terminal-header {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px 14px;
        background: #181825;
    }
    .dot { width: 11px; height: 11px; border-radius: 50%; }
    .dot.red    { background: #ff5f57; }
    .dot.yellow { background: #febc2e; }
    .dot.green  { background: #28c840; }
    .terminal-title { margin-left: 10px; color: #a6adc8; font-size: 12px; }

    .terminal-body {
        height: 340px;
        overflow-y: auto;
        padding: 16px 18px;
        font-family: 'Cascadia Code', 'Fira Code', 'Consolas', monospace;
        font-size: 12.5px;
        line-height: 1.6;
        color: #cdd6f4;
        white-space: pre-wrap;
        word-break: break-word;
    }
    .terminal-body::-webkit-scrollbar { width: 8px; }
    .terminal-body::-webkit-scrollbar-track { background: transparent; }
    .terminal-body::-webkit-scrollbar-thumb { background: #45475a; border-radius: 4px; }

    /* ===== Résultat final ===== */
    .status-banner {
        margin-top: 20px;
        padding: 14px 16px;
        border-left: 4px solid;
        border-radius: var(--radius);
        font-weight: 600;
    }
    .status-banner.success { background: var(--success-soft); border-color: var(--success); color: var(--success); }
    .status-banner.error   { background: var(--danger-soft);  border-color: var(--danger);  color: var(--danger); }

    .actions { display: flex; justify-content: flex-end; margin-top: 16px; }
    .btn-continue {
        display: inline-block;
        padding: 10px 22px;
        color: #fff;
        background: var(--primary);
        border: 1px solid var(--primary);
        border-radius: var(--radius);
        font-weight: 500;
        text-decoration: none;
    }
    .btn-continue:hover { filter: brightness(1.1); }
    .btn-continue:focus-visible { outline: 2px solid var(--primary); outline-offset: 2px; }

    @media (max-width: 600px) {
        main { padding: 16px 12px; }
    }
</style>
<script>
/* IMPORTANT : ce script est AVANT le <pre> dans le flux HTML.
   Placé après, il ne s'exécuterait qu'à la fin du streaming PHP,
   trop tard pour suivre la sortie en direct. */
(function () {
    // ===== Auto-scroll du terminal =====
    var followOutput = true;
    var terminal = null; // sera résolu dès que le <pre> existe

    var pollId = setInterval(function () {
        terminal = document.getElementById('terminal');
        if (!terminal) return;

        clearInterval(pollId);

        terminal.addEventListener('scroll', function () {
            var atBottom =
                terminal.scrollHeight - terminal.scrollTop - terminal.clientHeight < 40;
            followOutput = atBottom;
        });

        var observer = new MutationObserver(function () {
            if (followOutput) {
                terminal.scrollTop = terminal.scrollHeight;
            }
        });
        observer.observe(terminal, { childList: true, subtree: true, characterData: true });

        // Curseur clignotant tant que l'installation tourne
        var finishCheck = setInterval(function () {
            if (document.body.classList.contains('install-finished')) {
                terminal.classList.add('finished');
                clearInterval(finishCheck);
            }
        }, 300);
    }, 50);

    // ===== API publique pour piloter la carte d'étape =====
    window.InstallUI = {
        setStep: function (options) {
            /**
             * options = {
             *   number:      "Étape 2/5"         — petit label au-dessus
             *   title:       "Configuration MySQL" — titre principal
             *   description: "Création de la base…" — sous-texte
             *   progress:    42                   — % optionnel
             * }
             */
            var card = document.getElementById('stepCard');
            if (options.number)      document.getElementById('stepNumber').textContent = options.number;
            if (options.title)       document.getElementById('stepTitle').textContent = options.title;
            if (options.description) document.getElementById('stepDescription').textContent = options.description;
            if (typeof options.progress === 'number') {
                document.getElementById('progressFill').style.width = options.progress + '%';
                document.getElementById('progressLabel').textContent = Math.round(options.progress) + '% effectué';
            }
            card.className = 'card step-card'; // retire l'état "done"
        },

        done: function (message) {
            var card = document.getElementById('stepCard');
            card.classList.add('done');
            card.classList.toggle('failed', message === false);
            document.getElementById('stepSpinner').innerHTML = message === false ? '✗' : '✓';
            document.getElementById('stepTitle').textContent =
                message === false ? 'Échec de l\'installation' : 'Installation terminée';
            document.getElementById('stepDescription').textContent =
                message === false ? 'Consultez le terminal pour le détail de l\'erreur.'
                                  : 'Tout est prêt, vous pouvez continuer.';
            document.getElementById('progressFill').style.width = '100%';
            document.body.classList.add('install-finished');

            var banner = document.getElementById('warningBanner');
            if (banner) banner.style.display = 'none';
        }
    };
})();
</script>
</head>
<body>

<nav class="navbar">
    <img src="/build/images/RemoteLabz_logo_white.png" alt="">
    <span class="brand">RemoteLabz</span>
</nav>

<header class="jumbotron">
    <div class="inner">
        <h1>Installation de RemoteLabz</h1>
        <p>Suivi de l'installation en cours.</p>
    </div>
</header>

<main>

    <div class="warning-banner" id="warningBanner">
        <span class="icon">⚠️</span>
        <span>
            <strong>Ne fermez pas cette page</strong> pendant l'installation.
            Le processus d'installation s'exécute via cette page : la fermer
            ou rafraîchir interromprait l'opération et pourrait laisser le
            système dans un état incohérent.
        </span>
    </div>

    <!-- Carte d'étape -->
    <div class="card step-card" id="stepCard">
        <div class="step-spinner" id="stepSpinner"></div>
        <div class="step-number" id="stepNumber"></div>
        <div class="step-title" id="stepTitle"></div>
        <div class="step-description" id="stepDescription"></div>
        <div class="progress-bar"><div class="progress-fill" id="progressFill"></div></div>
        <div class="progress-label" id="progressLabel"></div>
    </div>

    <!-- Terminal replié par défaut -->
    <div class="terminal-wrapper" id="terminalWrapper">
        <button type="button" class="terminal-toggle"
                onclick="var w=document.getElementById('terminalWrapper');w.classList.toggle('open');var t=document.getElementById('terminal');if(w.classList.contains('open')){t.scrollTop=t.scrollHeight;}">
            <span class="arrow">▼</span>
            Afficher le journal détaillé
        </button>

        <div class="terminal-content">
            <div class="terminal-header">
                <span class="dot red"></span>
                <span class="dot yellow"></span>
                <span class="dot green"></span>
                <span class="terminal-title">install.sh</span>
            </div>
            <pre id="terminal" class="terminal-body"><?php $status = streamInstall($process, $pipes, $ctx); ?></pre>
        </div>
    </div>

    <?php if ($status === 0): ?>
        <div class="status-banner success">✓ Installation terminée avec succès</div>
        <div class="actions"><a class="btn-continue" href="<?php echo htmlspecialchars($continueLink) ?>">Continuer →</a></div>
    <?php else: ?>
        <div class="status-banner error">✗ Échec de l'installation (code <?php echo (int)$status; ?>)</div>
    <?php endif; ?>

</main>

    <script>
        // Passe la carte en état "terminé" quand la page est entièrement chargée
        window.addEventListener('load', function () {
            InstallUI.done(<?php echo $status === 0 ? 'true' : 'false'; ?>);
        });
    </script>


</body>
</html>

<?php }else {

header("Location: /");
die();

}?>