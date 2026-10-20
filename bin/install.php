<?php




function editEnv($key,$newVal){
    $cmd = 'sed -i "s|^'.$key.'=.*|'.$key.'='.$newVal.'|g" "/opt/remotelabz/.env.local"';
    shell_exec($cmd);
}

print_r($_POST);

if($_SERVER['REQUEST_METHOD'] == 'POST'){

while (ob_get_level() > 0) { ob_end_flush(); }
@ini_set('zlib.output_compression', '0');
@ini_set('output_buffering', 'off');


    shell_exec("mkdir /opt/remotelabz");
    
    $installType = $_POST["install_type"];

    $input = [];
    $continueLink = "/";

    switch($installType){
        case 'auto_install':

            if(!file_exists("/opt/remotelabz/.env.local")){
                shell_exec("cp ../.env /opt/remotelabz/.env.local");
            }
            
            
            editEnv("PUBLIC_ADDRESS",$_POST["ip_public"]);
            if($_POST['server_mode'] === "single_server"){
                editEnv("DEPLOY_SINGLE_SERVER", "1");
                editEnv("IP_ADDRESS","127.0.0.1");
            }else{
                editEnv("DEPLOY_SINGLE_SERVER", "0");
                editEnv("IP_ADDRESS",$_POST["ip_public"]);
            }

            editEnv("MYSQL_PASSWORD", $_POST["mysql_password"]);
            editEnv("SSL_CA_KEY_PASSPHRASE",$_POST["ssl_ca_key_password"]);

            editEnv("CONTACT_MAIL",$_POST["contact_email"]);

            $selfSigned = "n";

            if($_POST["self_signed"] === "yes"){$continueLink = "https://".$_POST["ip_public"];
                $selfSigned = "y";
            }


            //Param1 : type d'installation (1 = auto install)
            //Param2 : review conf file : ne pas mettre y sinon bloquage
            //param3 : selfsigned certificat
            $input[0] = "1\n";
            $input[1] = "n\n";
            $input[2] = $selfSigned."\n";
            
            $continueLink = "https://".$_POST["ip_public"];


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
            editEnv("SL_CA_KEY_PASSPHRASE",$_POST["openvpn_ssl_ca_key_password"]);

            $input[0] = "3\n";
            $input[1] = "\n";
            $input[2] = "7\n";

            break;
        case 'ssl':
            if(!file_exists("/opt/remotelabz/.env.local")){
                shell_exec("cp ../.env /opt/remotelabz/.env.local");
            }
            editEnv("PUBLIC_ADDRESS",$_POST["ssl_ip_public"]);
            if($_POST['ssl_server_mode'] === "single_server"){
                editEnv("DEPLOY_SINGLE_SERVER", "1");
                editEnv("IP_ADDRESS","127.0.0.1");
            }else{
                editEnv("DEPLOY_SINGLE_SERVER", "0");
                editEnv("IP_ADDRESS",$_POST["ssl_ip_public"]);
            }

            editEnv("MYSQL_PASSWORD", $_POST["ssl_mysql_password"]);
            editEnv("SSL_CA_KEY_PASSPHRASE",$_POST["ssl_ssl_ca_key_password"]);

            editEnv("CONTACT_MAIL",$_POST["ssl_contact_email"]);

            $selfSigned = "n";

            if($_POST["ssl_self_signed"] === "yes"){
                $selfSigned = "y";
            }
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
            editEnv("PUBLIC_ADDRESS",$_POST["rl_ip_public"]);
            if($_POST['rl_server_mode'] === "single_server"){
                editEnv("DEPLOY_SINGLE_SERVER", "1");
                editEnv("IP_ADDRESS","127.0.0.1");
            }else{
                editEnv("DEPLOY_SINGLE_SERVER", "0");
                editEnv("IP_ADDRESS",$_POST["rl_ip_public"]);
            }

            editEnv("MYSQL_PASSWORD", $_POST["rl_mysql_password"]);
            editEnv("SSL_CA_KEY_PASSPHRASE",$_POST["rl_ssl_ca_key_password"]);

            editEnv("CONTACT_MAIL",$_POST["rl_contact_email"]);

            $selfSigned = "n";

            if($_POST["ssl_self_signed"] === "yes"){
                $selfSigned = "y";
            }
            $input[0] = "5\n";
            $input[1] = "n\n";
            $input[2] = $selfSigned."\n";

            $continueLink = "https://".$_POST["rl_ip_public"];


            break;
        case 'custom':
            echo "custom";
            break;
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
                            'HOME' => '/home/vboxuser',
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
<title>Installation en cours...</title>
<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Segoe UI', Tahoma, sans-serif;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        padding: 30px;
    }

    /* ===== Carte d'étape ===== */
    .step-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 15px 40px rgba(0, 0, 0, 0.3);
        padding: 36px 48px;
        width: 100%;
        max-width: 520px;
        text-align: center;
        z-index: 2;
    }

    .step-spinner {
        width: 56px;
        height: 56px;
        margin: 0 auto 20px;
        border: 5px solid #ede7fb;
        border-top-color: #764ba2;
        border-radius: 50%;
        animation: spin 0.9s linear infinite;
    }

    .step-card.done .step-spinner {
        animation: none;
        border: none;
        width: auto;
        height: auto;
        font-size: 52px;
        line-height: 1;
    }

    .step-card.done .step-spinner { content: ''; }

    .step-number {
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 2px;
        text-transform: uppercase;
        color: #a08cd6;
        margin-bottom: 6px;
    }

    .step-title {
        font-size: 24px;
        font-weight: 600;
        color: #333;
        margin-bottom: 10px;
    }

    .step-description {
        font-size: 14px;
        color: #777;
        line-height: 1.5;
    }

    .progress-bar {
        margin-top: 24px;
        height: 6px;
        background: #ede7fb;
        border-radius: 3px;
        overflow: hidden;
    }

    .progress-fill {
        height: 100%;
        width: 0%;
        background: linear-gradient(90deg, #764ba2, #667eea);
        border-radius: 3px;
        transition: width 0.4s ease;
    }

    .progress-label {
        margin-top: 8px;
        font-size: 12px;
        color: #999;
    }

    @keyframes spin {
        to { transform: rotate(360deg); }
    }

    /* ===== Terminal repliable ===== */
    .terminal-wrapper {
        width: 100%;
        max-width: 720px;
        margin-top: 28px;
        z-index: 2;
    }

    .terminal-toggle {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        width: 100%;
        padding: 13px;
        background: rgba(30, 30, 46, 0.55);
        backdrop-filter: blur(6px);
        color: #d8d4ee;
        font-size: 14px;
        font-weight: 500;
        border: 1px solid rgba(255, 255, 255, 0.15);
        border-radius: 10px;
        cursor: pointer;
        transition: background 0.2s;
    }

    .terminal-toggle:hover {
        background: rgba(30, 30, 46, 0.75);
    }

    .terminal-toggle .arrow {
        transition: transform 0.3s ease;
    }

    .terminal-wrapper.open .terminal-toggle .arrow {
        transform: rotate(180deg);
    }

    /* Replié par défaut */
    .terminal-content {
        display: none;
        flex-direction: column;
        margin-top: 10px;
        background: #1e1e2e;
        border-radius: 12px;
        box-shadow: 0 15px 40px rgba(0, 0, 0, 0.45);
        overflow: hidden;
    }

    .terminal-wrapper.open .terminal-content {
        display: flex;
    }

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

    .terminal-title {
        margin-left: 10px;
        color: #a6adc8;
        font-size: 12px;
    }

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

    /* ===== Bandeau final ===== */
    .status-banner {
        display: none;
        width: 100%;
        max-width: 520px;
        margin-top: 28px;
        padding: 16px 20px;
        border-radius: 12px;
        font-size: 15px;
        font-weight: 600;
        text-align: center;
        z-index: 2;
    }

    .status-banner.success { display: block; background: #1e3a2f; color: #a6e3a1; }
    .status-banner.error   { display: block; background: #3b2229; color: #f38ba8; }

    .btn-continue {
        display: inline-block;
        margin-top: 24px;
        padding: 14px 48px;
        background: linear-gradient(135deg, #764ba2, #667eea);
        color: white;
        text-decoration: none;
        border-radius: 10px;
        font-weight: 600;
        transition: transform 0.2s;
    }

    .btn-continue:hover { transform: translateY(-2px); }

    .warning-banner {
    width: 100%;
    max-width: 520px;
    margin-bottom: 24px;
    padding: 14px 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    background: rgba(255, 220, 120, 0.15);
    border: 1px solid rgba(230, 170, 60, 0.5);
    border-radius: 12px;
    color: #ffd08a;
    font-size: 14px;
    font-weight: 500;
    line-height: 1.5;
    z-index: 2;
    backdrop-filter: blur(6px);
}

.warning-banner .icon {
    font-size: 20px;
    line-height: 1;
    flex-shrink: 0;
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
            card.className = 'step-card'; // retire l'état "done"
        },

        done: function (message) {
            var card = document.getElementById('stepCard');
            card.classList.add('done');
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


<div class="warning-banner" id="warningBanner">
    <span class="icon">⚠️</span>
    <span>
        <strong>Ne fermez pas cette page</strong> pendant l'installation.
        Le processus d'installation s'exécute via cette page : la fermer
        ou rafraîchir interromprait l'opération et pourrait laisser le
        système dans un état incohérent.
    </span>
</div>

    <!-- Carte d'étape (au centre) -->
    <div class="step-card" id="stepCard">
        <div class="step-spinner" id="stepSpinner"></div>
        <div class="step-title" id="stepTitle"></div>
        <div class="step-description" id="stepDescription">
        </div>
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
                
                    while ($s = fgets($pipes[1])) {

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
                            case 'custom':
                                echo "custom";
                                break;
                            default:
                                die();
                        }

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
                        if(str_contains($s,"Final Configuration")){
                            echo '<script>InstallUI.setStep({"title":"Final Configuration"})</script>';
                        }

                        if(str_contains($s, "[0;32m")){
                            $s = str_replace("[0;32m","",$s);
                            $s = str_replace("[0m","",$s);
                            echo '<script>InstallUI.setStep({"description":"'.json_encode($s).'"})</script>';
                        }

                        $s = preg_replace('/\[\d\d?;?\d?\d?m/',"",$s);


                        print $s;
                        
                        flush();
                    }

                    fclose($pipes[0]);

                    $status = proc_close($process);
                
            ?></pre>
        </div>
    </div>
    <?php if ($status === 0): ?>
        <div class="status-banner success">✓ Installation terminée avec succès</div>
        <a class="btn-continue" href="<?php echo $continueLink ?>">Continuer →</a>
    <?php else: ?>
        <div class="status-banner error">✗ Échec de l'installation (code <?php echo (int)$status; ?>)</div>
    <?php endif; ?>

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

