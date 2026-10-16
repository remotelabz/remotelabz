<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installer Web</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            padding: 40px;
            width: 100%;
            max-width: 560px;
        }

        h1 {
            color: #333;
            text-align: center;
            margin-bottom: 30px;
            font-size: 28px;
            font-weight: 600;
        }

        .form-group {
            margin-bottom: 25px;
        }

        label {
            display: block;
            color: #555;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 8px;
        }

        /* ===== Sélecteur du type d'installation ===== */
        .install-type-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .install-type {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            padding: 12px 14px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            transition: all 0.25s ease;
            font-size: 14px;
            font-weight: 500;
            color: #555;
        }

        .install-type:hover {
            border-color: #764ba2;
            background: #f8f5ff;
        }

        .install-type.active {
            border-color: #764ba2;
            background: #f3effc;
            color: #5a3a9e;
        }

        .install-type input[type="radio"] {
            accent-color: #764ba2;
            margin: 0;
        }

        /* ===== Groupes conditionnels ===== */
        .conditional-group {
            display: none;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px dashed #e0d6f5;
        }

        .conditional-group.visible {
            display: block;
        }

        /* ===== Sous-groupes conditionnels (ex : autosigné si SSL coché) ===== */
        .sub-conditional {
            display: none;
            margin-top: 12px;
            padding-left: 20px;
        }

        .sub-conditional.visible {
            display: block;
        }

        /* ===== Radio group générique ===== */
        .radio-group {
            display: flex;
            gap: 15px;
            margin-top: 5px;
        }

        .radio-option {
            display: flex;
            align-items: center;
            cursor: pointer;
            padding: 12px 18px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            transition: all 0.25s ease;
            flex: 1;
            font-size: 14px;
        }

        .radio-option:hover {
            border-color: #764ba2;
            background: #f8f5ff;
        }

        .radio-option input[type="radio"],
        .radio-option input[type="checkbox"] {
            margin-right: 10px;
            accent-color: #764ba2;
        }

        .radio-option.active {
            border-color: #764ba2;
            background: #f3effc;
        }

        /* ===== Checkboxes (custom) ===== */
        .checkbox-option {
            display: flex;
            align-items: center;
            cursor: pointer;
            padding: 12px 14px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            transition: all 0.25s ease;
            font-size: 14px;
            margin-bottom: 10px;
        }

        .checkbox-option:hover {
            border-color: #764ba2;
            background: #f8f5ff;
        }

        .checkbox-option.active {
            border-color: #764ba2;
            background: #f3effc;
        }

        .checkbox-option input {
            margin-right: 10px;
            accent-color: #764ba2;
            width: 17px;
            height: 17px;
        }

        input[type="text"],
        input[type="password"],
        input[type="email"] {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.25s ease;
            outline: none;
        }

        input[type="text"]:focus,
        input[type="password"]:focus,
        input[type="email"]:focus {
            border-color: #764ba2;
            box-shadow: 0 0 0 3px rgba(118, 75, 162, 0.1);
        }

        input[type="text"]:disabled,
        input[type="password"]:disabled,
        input[type="email"]:disabled {
            background: #f0f0f0;
            color: #aaa;
            cursor: not-allowed;
        }

        input::placeholder {
            color: #aaa;
        }

        button {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 10px;
            transition: all 0.3s ease;
        }

        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(118, 75, 162, 0.4);
        }

        button:active {
            transform: translateY(0);
        }

        .required {
            color: #e74c3c;
            margin-left: 4px;
        }

        .hint {
            font-size: 12px;
            color: #888;
            margin-top: 5px;
        }

        /* Message pour les types sans configuration */
        .no-config-message {
            text-align: center;
            color: #777;
            font-size: 14px;
            background: #f8f5ff;
            border: 1px dashed #d6c8f0;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Configuration Installer</h1>

        <form id="installerForm" method="post" action="./install.php">

            <!-- ===== Type d'installation ===== -->
            <div class="form-group">
                <label>Type d'installation<span class="required">*</span></label>
                <div class="install-type-group">
                    <label class="install-type active">
                        <input type="radio" name="install_type" value="auto_install" checked onchange="updateForm()">
                        Auto Install (RECOMMANDÉ)
                    </label>
                    <label class="install-type">
                        <input type="radio" name="install_type" value="requirements" onchange="updateForm()">
                        Requirements
                    </label>
                    <label class="install-type">
                        <input type="radio" name="install_type" value="openvpn" onchange="updateForm()">
                        OpenVPN
                    </label>
                    <label class="install-type">
                        <input type="radio" name="install_type" value="ssl" onchange="updateForm()">
                        SSL
                    </label>
                    <label class="install-type">
                        <input type="radio" name="install_type" value="remotelabz" onchange="updateForm()">
                        RemoteLabz
                    </label>
                    <label class="install-type">
                        <input type="radio" name="install_type" value="custom" onchange="updateForm()">
                        Custom
                    </label>
                </div>
            </div>

            <!-- ================================================== -->
            <!-- 1. AUTO INSTALL                                    -->
            <!-- ================================================== -->
            <div class="conditional-group" id="group-auto_install">

                <div class="form-group">
                    <label for="IpPublic">IP Publique<span class="required">*</span></label>
                    <input type="text" id="IpPublic" name="ip_public"
                           placeholder="Ex: 203.0.113.10" required>
                </div>

                <div class="form-group">
                    <label>Architecture serveur<span class="required">*</span></label>
                    <div class="radio-group">
                        <label class="radio-option active">
                            <input type="radio" name="server_mode" value="single_server" checked onchange="toggleRadio(this)">
                            Single serveur
                        </label>
                        <label class="radio-option">                            <input type="radio" name="server_mode" value="multiserver" onchange="toggleRadio(this)">
                            Multiserveur
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label for="MysqlPassword">Mot de passe MySQL<span class="required">*</span></label>
                    <input type="password" id="MysqlPassword" name="mysql_password"
                           placeholder="••••••••" required>
                    <div class="hint">Accès à la base de données</div>
                </div>

                <div class="form-group">
                    <label for="CertPassword">Mot de passe SSL_CA_KEY<span class="required">*</span></label>
                    <input type="password" id="CertPassword" name="ssl_ca_key_password"
                           placeholder="••••••••" required>
                    <div class="hint">Protection de la clé de l'autorité de certification</div>
                </div>

                <div class="form-group">
                    <label for="ContactEmail">Email de contact<span class="required">*</span></label>
                    <input type="email" id="ContactEmail" name="contact_email"
                           placeholder="admin@exemple.com" required>
                </div>

                <div class="form-group">
                    <label>Certificat auto-signé<span class="required">*</span></label>
                    <div class="radio-group">
                        <label class="radio-option active">
                            <input type="radio" name="self_signed" value="yes" checked onchange="toggleRadio(this)">
                            Oui
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="self_signed" value="no" onchange="toggleRadio(this)">
                            Non
                        </label>
                    </div>
                    <div class="hint">Un certificat auto-signé ne sera pas reconnu par défaut par les navigateurs</div>
                </div>
            </div>

            <!-- ================================================== -->
            <!-- 2. REQUIREMENTS                                    -->
            <!-- ================================================== -->
            <div class="conditional-group" id="group-requirements">
                <div class="no-config-message">
                    <strong>Installation des requirements</strong><br>
                    Aucune configuration n'est nécessaire.<br>
                    Les dépendances système seront installées automatiquement.
                </div>
            </div>

            <!-- ================================================== -->
            <!-- 3. OPENVPN                                         -->
            <!-- ================================================== -->
            <div class="conditional-group" id="group-openvpn">

                <div class="form-group">
                    <label for="CertPassword">Mot de passe SSL_CA_KEY<span class="required">*</span></label>
                    <input type="password" id="CertPassword" name="openvpn_ssl_ca_key_password"
                           placeholder="••••••••" required>
                    <div class="hint">Protection de la clé de l'autorité de certification</div>
                </div>
            </div>

            <!-- ================================================== -->
            <!-- 4. SSL                                             -->
            <!-- ================================================== -->
            <div class="conditional-group" id="group-ssl">

                <div class="form-group">
                    <label for="sslIpPublic">IP Publique<span class="required">*</span></label>
                    <input type="text" id="sslIpPublic" name="ssl_ip_public"
                           placeholder="Ex: 203.0.113.10" required>
                </div>

                <div class="form-group">
                    <label>Architecture serveur<span class="required">*</span></label>
                    <div class="radio-group">
                        <label class="radio-option active">
                            <input type="radio" name="ssl_server_mode" value="single_server" checked onchange="toggleRadio(this)">
                            Single serveur
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="ssl_server_mode" value="multiserver" onchange="toggleRadio(this)">
                            Multiserveur
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label for="MysqlPassword">Mot de passe MySQL<span class="required">*</span></label>
                    <input type="password" id="MysqlPassword" name="ssl_mysql_password"
                           placeholder="••••••••" required>
                </div>

                <div class="form-group">
                    <label for="CertPassword">Mot de passe SSL_CA_KEY<span class="required">*</span></label>
                    <input type="password" id="CertPassword" name="ssl_ssl_ca_key_password"
                           placeholder="••••••••" required>
                    <div class="hint">Protection de la clé de l'autorité de certification</div>
                </div>

                <div class="form-group">
                    <label for="ContactEmail">Email de contact<span class="required">*</span></label>
                    <input type="email" id="ContactEmail" name="ssl_contact_email"
                           placeholder="admin@exemple.com" required>
                </div>

                <div class="form-group">
                    <label>Certificat auto-signé<span class="required">*</span></label>
                    <div class="radio-group">
                        <label class="radio-option active">
                            <input type="radio" name="ssl_self_signed" value="yes" checked onchange="toggleRadio(this)">
                            Oui
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="ssl_self_signed" value="no" onchange="toggleRadio(this)">
                            Non
                        </label>
                    </div>
                </div>
            </div>

            <!-- ================================================== -->
            <!-- 5. REMOTELABZ                                      -->
            <!-- ================================================== -->
            <div class="conditional-group" id="group-remotelabz">
                <div class="form-group">
                    <label for="sslIpPublic">IP Publique<span class="required">*</span></label>
                    <input type="text" id="sslIpPublic" name="rl_ip_public"
                           placeholder="Ex: 203.0.113.10" required>
                </div>

                <div class="form-group">
                    <label>Architecture serveur<span class="required">*</span></label>
                    <div class="radio-group">
                        <label class="radio-option active">
                            <input type="radio" name="rl_server_mode" value="single_server" checked onchange="toggleRadio(this)">
                            Single serveur
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="rl_server_mode" value="multiserver" onchange="toggleRadio(this)">
                            Multiserveur
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label for="MysqlPassword">Mot de passe MySQL<span class="required">*</span></label>
                    <input type="password" id="MysqlPassword" name="rl_mysql_password"
                           placeholder="••••••••" required>
                </div>

                <div class="form-group">
                    <label for="CertPassword">Mot de passe SSL_CA_KEY<span class="required">*</span></label>
                    <input type="password" id="CertPassword" name="rl_ssl_ca_key_password"
                           placeholder="••••••••" required>
                    <div class="hint">Protection de la clé de l'autorité de certification</div>
                </div>

                <div class="form-group">
                    <label for="ContactEmail">Email de contact<span class="required">*</span></label>
                    <input type="email" id="ContactEmail" name="rl_contact_email"
                           placeholder="admin@exemple.com" required>
                </div>

                <div class="form-group">
                    <label>Certificat auto-signé<span class="required">*</span></label>
                    <div class="radio-group">
                        <label class="radio-option active">
                            <input type="radio" name="rl_self_signed" value="yes" checked onchange="toggleRadio(this)">
                            Oui
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="rl_self_signed" value="no" onchange="toggleRadio(this)">
                            Non
                        </label>
                    </div>
                </div>
            </div>

            <!-- ================================================== -->
            <!-- 6. CUSTOM                                          -->
            <!-- ================================================== -->
            <div class="conditional-group" id="group-custom">
                <div class="group-title">Composants à installer</div>

                <label class="checkbox-option active">
                    <input type="checkbox" name="custom_requirements" checked onchange="toggleCheckbox(this)">
                    Installer les requirements
                </label>

                <label class="checkbox-option">
                    <input type="checkbox" name="custom_openvpn" onchange="toggleCheckbox(this)">
                    Installer et configurer OpenVPN
                </label>

                <label class="checkbox-option">
                    <input type="checkbox" name="custom_system_params" onchange="toggleCheckbox(this)">
                    Configurer les paramètres système
                </label>

                <label class="checkbox-option" id="sslCheckboxOption">
                    <input type="checkbox" name="custom_ssl" id="customSslCheckbox" onchange="toggleCheckbox(this); updateSubConditional()">
                    Configurer le SSL
                </label>

                <!-- Sous-options : visible seulement si "Configurer le SSL" est coché -->
                <div class="sub-conditional" id="sub-ssl-options">
                    <div class="form-group">
                        <label>Certificat auto-signé<span class="required">*</span></label>
                        <div class="radio-group">
                            <label class="radio-option active">
                                <input type="radio" name="custom_ssl_self_signed" value="yes" checked onchange="toggleRadio(this)">
                                Oui
                            </label>
                            <label class="radio-option">
                                <input type="radio" name="custom_ssl_self_signed" value="no" onchange="toggleRadio(this)">
                                Non
                            </label>
                        </div>
                    </div>
                </div>

                <label class="checkbox-option">
                    <input type="checkbox" name="custom_remotelabz" onchange="toggleCheckbox(this)">
                    Installer RemoteLabz
                </label>
            </div>

            <!-- Bouton de soumission -->
            <button type="submit">Lancer l'installation</button>
        </form>
    </div>

    <script>
        // ===== Visuel des radios génériques =====
        function toggleRadio(radio) {
            var siblings = radio.closest('.radio-group').querySelectorAll('.radio-option');
            siblings.forEach(function (option) {
                option.classList.remove('active');
            });
            radio.closest('.radio-option').classList.add('active');
        }

        // ===== Visuel des checkboxes =====
        function toggleCheckbox(checkbox) {
            checkbox.closest('.checkbox-option').classList.toggle('active', checkbox.checked);
        }

        // ===== Affiche le sous-groupe SSL si coché =====
        function updateSubConditional() {
            var sslChecked = document.getElementById('customSslCheckbox').checked;
            document.getElementById('sub-ssl-options').classList.toggle('visible', sslChecked);
        }

        // ===== Bascule entre les groupes selon le type choisi =====
        // VERSION CORRIGÉE : utilisation de disabled au lieu de required
        function updateForm() {
            var selected = document.querySelector('input[name="install_type"]:checked').value;

            // 1. Masque tous les groupes et désactive leurs inputs
            document.querySelectorAll('.conditional-group').forEach(function (group) {
                group.classList.remove('visible');
                
                // Désactive tous les inputs de ce groupe (sauf les radios/checkboxes de contrôle)
                group.querySelectorAll('input[type="text"], input[type="password"], input[type="email"]').forEach(function (input) {
                    input.disabled = true;
                });
            });

            // 2. Réactive uniquement le groupe visible
            var target = document.getElementById('group-' + selected);
            if (target) {
                target.classList.add('visible');
                
                // Réactive tous les inputs de ce groupe
                target.querySelectorAll('input[type="text"], input[type="password"], input[type="email"]').forEach(function (input) {
                    input.disabled = false;
                });
            }
        }

        // Initialisation
        document.addEventListener('DOMContentLoaded', function () {
            // Initialement, tous les groupes conditionnels sont masqués ET désactivés
            document.querySelectorAll('.conditional-group').forEach(function (group) {
                group.querySelectorAll('input[type="text"], input[type="password"], input[type="email"]').forEach(function (input) {
                    input.disabled = true;
                });
            });
            
            // Puis activer le premier groupe (Auto Install)
            updateForm();
        });
    </script>
</body>
</html>