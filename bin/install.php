<?php

/* =====================================================================
   RÉGLAGES À ADAPTER À install.sh
   Les textes ci-dessous sont recherchés (sans tenir compte de la casse)
   dans la sortie d'install.sh : dès qu'une ligne en contient un, la
   réponse correspondante est envoyée sur son entrée standard.
   Ce sont des hypothèses : remplacez-les par le texte exact des prompts.
   ===================================================================== */
const WORKER_MARKERS = [
    'deploy'          => 'deploy workers',          // "Déploiement de workers ? (y/n)"
    'count'           => 'number of workers',       // nombre de workers
    'user'            => 'worker user account',     // début des questions d'un worker
    'topology'        => 'topology',
    'vpn'             => 'vpn hosted',
    'worker_password' => 'worker password',
    'front_password'  => 'front password',
    'done'            => 'Worker deployment completed', // fin (type "workers" seul)
];

// Réponse envoyée à install.sh pour la topologie
const TOPOLOGY_ANSWERS = [
    'single_server' => '1',
    'multiserver'   => '2',
];

const MAX_WORKERS = 20;


/* ===================== Fonctions utilitaires ===================== */

function fail($msg){
    http_response_code(400);
    echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Erreur</title></head>'
       . '<body><pre>' . htmlspecialchars($msg) . '</pre></body></html>';
    exit;
}

// Lit un champ POST texte. Refuse les retours à la ligne : la valeur est envoyée
// sur stdin d'install.sh ou écrite dans .env.local, un \n permettrait d'injecter
// d'autres réponses ou d'autres variables.
function post($key){
    if(!isset($_POST[$key]) || !is_string($_POST[$key])){
        fail("Champ manquant : $key");
    }
    $v = $_POST[$key];
    if(preg_match('/[\r\n\0]/', $v)){
        fail("Valeur invalide pour : $key");
    }
    return $v;
}

// Modifie une variable existante de .env.local sans passer par le shell
// (le sed d'origine cassait avec |, &, \ ou " dans un mot de passe
// et permettait l'injection de commandes).
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
// ($prefix = nom du tableau POST, $countKey = champ "nombre de workers")
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
        $w = $list[$i] ?? null;
        if(!is_array($w)){
            fail("Worker " . ($i + 1) . " : informations manquantes.");
        }
        $row = [];
        foreach(['user', 'topology', 'vpn', 'worker_password', 'front_password'] as $f){
            $v = $w[$f] ?? '';
            if(!is_string($v) || $v === '' || preg_match('/[\r\n\0]/', $v)){
                fail("Worker " . ($i + 1) . " : champ « $f » invalide.");
            }
            $row[$f] = $v;
        }
        if(!isset(TOPOLOGY_ANSWERS[$row['topology']])){
            fail("Worker " . ($i + 1) . " : topologie invalide.");
        }
        if(!in_array($row['vpn'], ['yes', 'no'], true)){
            fail("Worker " . ($i + 1) . " : valeur VPN invalide.");
        }
        $workers[] = $row;
    }
    return $workers;
}

// Répond aux questions d'install.sh sur les workers, dans l'ordre où elles arrivent.
// Chaque réponse n'est envoyée qu'une fois.
function answerWorkerPrompts($line, $stdin, &$st){
    if(!$st['enabled']){
        return;
    }
    $has = function ($key) use ($line) {
        return stripos($line, WORKER_MARKERS[$key]) !== false;
    };
    $total = count($st['workers']);

    if(!$st['deploy_done'] && $has('deploy')){
        fwrite($stdin, ($total > 0 ? "y" : "n") . "\n");
        $st['deploy_done'] = true;
        return;
    }
    if($total === 0){
        return;
    }
    if(!$st['count_done'] && $has('count')){
        fwrite($stdin, $total . "\n");
        $st['count_done'] = true;
        return;
    }
    if($has('user')){
        $st['index']++;
        if($st['index'] < $total){
            fwrite($stdin, $st['workers'][$st['index']]['user'] . "\n");
            $st['answered'][$st['index']]['user'] = true;
        }
        return;
    }

    $i = $st['index'];
    if($i < 0 || $i >= $total){
        return;
    }
    foreach(['front_password', 'worker_password', 'vpn', 'topology'] as $f){
        if($has($f)){
            if(!empty($st['answered'][$i][$f])){
                return;
            }
            $value = $st['workers'][$i][$f];
            if($f === 'vpn'){
                $value = ($value === 'yes') ? 'y' : 'n';
            }elseif($f === 'topology'){
                $value = TOPOLOGY_ANSWERS[$value];
            }
            fwrite($stdin, $value . "\n");
            $st['answered'][$i][$f] = true;
            return;
        }
    }
}


/* ============================ Traitement ============================ */

if($_SERVER['REQUEST_METHOD'] == 'POST'){

while (ob_get_level() > 0) { ob_end_flush(); }
@ini_set('zlib.output_compression', '0');
@ini_set('output_buffering', 'off');


    shell_exec("mkdir /opt/remotelabz");

    $installType = post("install_type");

    $input = [];
    $continueLink = "/";
    $workers = [];
    $workersEnabled = false;

    switch($installType){
        case 'auto_install':

            if(!file_exists("/opt/remotelabz/.env.local")){
                shell_exec("cp ../.env /opt/remotelabz/.env.local");
            }

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

            $selfSigned = (post("self_signed") === "yes") ? "y" : "n";

            // Déploiement de workers
            $workersEnabled = true;
            if(post("deploy_workers") === "yes"){
                $workers = readWorkers("auto_workers", "auto_workers_count");
            }

            //Param1 : type d'installation (1 = auto install)
            //Param2 : review conf file : ne pas mettre y sinon bloquage
            //param3 : selfsigned certificat
            $input[0] = "1\n";
            $input[1] = "n\n";
            $input[2] = $selfSigned."\n";

            $continueLink = "https://".post("ip_public");

            break;
        case 'requirements':
            $input[0] = "2\n";
            $input[1] = "\n";
            $input[2] = "7\n";
            break;
        case 'openvpn':
            if(!file_exists("/opt/remotelabz/.env.local")){
                shell_exec("cp ../.env /opt/remotelabz/.env.local");
            }
            // (la clé d'origine "SL_CA_KEY_PASSPHRASE" était une faute de frappe)
            editEnv("SSL_CA_KEY_PASSPHRASE", post("openvpn_ssl_ca_key_password"));

            $input[0] = "3\n";
            $input[1] = "\n";
            $input[2] = "7\n";

            break;
        case 'ssl':
            if(!file_exists("/opt/remotelabz/.env.local")){
                shell_exec("cp ../.env /opt/remotelabz/.env.local");
            }
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

            $selfSigned = (post("ssl_self_signed") === "yes") ? "y" : "n";

            $input[0] = "4\n";
            $input[1] = "n\n";
            $input[2] = $selfSigned."\n";
            $input[3] = "\n";
            $input[4] = "7\n";

            break;
        case 'remotelabz':
            if(!file_exists("/opt/remotelabz/.env.local")){
                shell_exec("cp ../.env /opt/remotelabz/.env.local");
            }
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

            // (l'ancien code lisait "ssl_self_signed" au lieu de "rl_self_signed")
            $selfSigned = (post("rl_self_signed") === "yes") ? "y" : "n";

            $input[0] = "5\n";
            $input[1] = "n\n";
            $input[2] = $selfSigned."\n";

            $continueLink = "https://".post("rl_ip_public");


            break;
        case 'workers':
            if(!file_exists("/opt/remotelabz/.env.local")){
                shell_exec("cp ../.env /opt/remotelabz/.env.local");
            }

            $workersEnabled = true;
            $workers = readWorkers("workers", "workers_count");

            // Hypothèse : 6 = "Configure worker deployments" dans le menu d'install.sh
            $input[0] = "6\n";
            $input[1] = "\n";
            $input[2] = "7\n";

            break;
        case 'custom':
            echo "custom";
            break;
        default:
            die();
    }

    $workerState = [
        'enabled'     => $workersEnabled,
        'workers'     => $workers,
        'deploy_done' => false,
        'count_done'  => false,
        'index'       => -1,
        'answered'    => [],
    ];



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
                            'HOME' => '\/home\/'.get_current_user(),
                            'PATH' => getenv("PATH")
                            ));


    if (!is_resource($process)) {
        http_response_code(500);
        echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Erreur</title></head>'
        . '<body><pre>Erreur : impossible de démarrer le processus d\'installation.</pre></body></html>';
        exit;
    }

    fwrite($pipes[0],$input[0]);

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
            <pre id="terminal" class="terminal-body"><?php

                    while (false !== ($s = fgets($pipes[1]))) {

                        // Ligne sans codes couleur, pour la recherche des prompts
                        $plain = preg_replace('/\x1b?\[[0-9;]*m/', '', $s);

                        switch($installType){
                            case 'auto_install':
                                if(str_contains($s,"Existing environment file loaded")){
                                    fwrite($pipes[0],$input[1]);
                                }

                                if(str_contains($s,"Enabling WSS")){
                                    fwrite($pipes[0],$input[2]);
                                }
                                break;
                            case 'requirements':
                                if(str_contains($s,"System requirements installation completed")){
                                    fwrite($pipes[0], $input[1]);
                                    fwrite($pipes[0], $input[2]);
                                }

                                break;
                            case 'openvpn':
                                if(str_contains($s,"OpenVPN setup completed")){
                                    fwrite($pipes[0],$input[1]);
                                    fwrite($pipes[0],$input[2]);
                                }
                                break;
                            case 'ssl':
                                if(str_contains($s,"Existing environment file loaded")){
                                    fwrite($pipes[0],$input[1]);
                                }

                                if(str_contains($s,"Enabling WSS")){
                                    fwrite($pipes[0],$input[2]);
                                }

                                if(str_contains($s,"SSL configuration completed")){
                                    fwrite($pipes[0],$input[3]);
                                    fwrite($pipes[0],$input[4]);

                                }

                                break;
                            case 'remotelabz':
                                if(str_contains($s,"Existing environment file loaded")){
                                    fwrite($pipes[0],$input[1]);
                                }

                                break;
                            case 'workers':
                                if(stripos($plain, WORKER_MARKERS['done']) !== false){
                                    fwrite($pipes[0],$input[1]);
                                    fwrite($pipes[0],$input[2]);
                                }
                                break;
                            case 'custom':
                                echo "custom";
                                break;
                            default:
                                die();
                        }

                        // Questions sur les workers (auto_install et workers)
                        answerWorkerPrompts($plain, $pipes[0], $workerState);

                        if(str_contains($s,"Installing System Requirements")){
                            echo '<script>InstallUI.setStep({"title":"Installing System Requirements"})</script>';
                        }
                        if(str_contains($s,"Setting up OpenVPN")){
                            echo '<script>InstallUI.setStep({"title":"Setting up OpenVPN"})</script>';
                        }
                        if(str_contains($s,"Configuring System Parameters")){
                            echo '<script>InstallUI.setStep({"title":"Configuring System Parameters"})</script>';
                        }
                        if(str_contains($s,"Configuring SSL Certificates")){
                            echo '<script>InstallUI.setStep({"title":"Configuring SSL Certificates"})</script>';
                        }
                        if(str_contains($s,"Installing RemoteLabz Application")){
                            echo '<script>InstallUI.setStep({"title":"Installing RemoteLabz Application"})</script>';
                        }
                        if(str_contains($s,"Deploying Workers") || str_contains($s,"Configuring Worker")){
                            echo '<script>InstallUI.setStep({"title":"Configuring Worker Deployments"})</script>';
                        }
                        if(str_contains($s,"Final Configuration")){
                            echo '<script>InstallUI.setStep({"title":"Final Configuration"})</script>';
                        }

                        if(str_contains($s, "[0;32m")){
                            // json_encode ajoute déjà les guillemets : on encode l'objet entier
                            echo '<script>InstallUI.setStep('
                                . json_encode(['description' => trim($plain)], JSON_HEX_TAG | JSON_UNESCAPED_UNICODE)
                                . ')</script>';
                        }

                        print htmlspecialchars($plain, ENT_SUBSTITUTE);

                        flush();
                    }

                    fclose($pipes[0]);

                    $status = proc_close($process);

            ?></pre>
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