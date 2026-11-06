<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
<title>RemoteLabz – Installer</title>
<style>
  /* Palette : à ajuster sur les variables de /build/app.*.css si besoin */
  :root {
    --bg: #f4f6fa;
    --surface: #ffffff;
    --text: #212529;
    --muted: #6c757d;
    --border: #dee2e6;
    --nav: #1f2a3c;
    --primary: #2f6fdb;
    --primary-soft: #e8f0fd;
    --danger: #dc3545;
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

  /* Barre de navigation */
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

  /* En-tête */
  .jumbotron {
    background: var(--surface);
    border-bottom: 1px solid var(--border);
    padding: 28px 24px;
  }
  .jumbotron .inner { max-width: 960px; margin: 0 auto; }
  .jumbotron h1 { font-size: 30px; font-weight: 300; }
  .jumbotron p { color: var(--muted); margin-top: 4px; }

  main { flex: 1; width: 100%; max-width: 960px; margin: 0 auto; padding: 28px 24px; }

  .card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    margin-bottom: 20px;
  }
  .card-header {
    padding: 14px 20px;
    border-bottom: 1px solid var(--border);
    font-weight: 600;
  }
  .card-body { padding: 20px; }

  /* Choix du type d'installation */
  .type-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 10px; }
  .type-option {
    display: flex; gap: 10px; align-items: flex-start;
    padding: 12px 14px;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    cursor: pointer;
  }
  .type-option:hover { border-color: var(--primary); }
  .type-option:has(input:checked) { border-color: var(--primary); background: var(--primary-soft); }
  .type-option:focus-within { outline: 2px solid var(--primary); outline-offset: 1px; }
  .type-option input { accent-color: var(--primary); margin-top: 4px; }
  .type-option strong { display: block; font-weight: 600; }
  .type-option small { color: var(--muted); font-size: 13px; }
  .badge {
    display: inline-block; margin-left: 6px; padding: 1px 8px;
    background: var(--primary); color: #fff; border-radius: 10px;
    font-size: 11px; font-weight: 600; vertical-align: middle;
  }

  /* Groupes conditionnels */
  .conditional-group { display: none; }
  .conditional-group.visible { display: block; }
  .sub-conditional { display: none; margin: 4px 0 14px 28px; padding-left: 14px; border-left: 3px solid var(--primary-soft); }
  .sub-conditional.visible { display: block; }

  /* Champs */
  .form-group { margin-bottom: 18px; }
  label.field-label { display: block; font-weight: 500; margin-bottom: 6px; }
  .required { color: var(--danger); margin-left: 3px; }
  .hint { font-size: 13px; color: var(--muted); margin-top: 4px; }
  input[type="text"], input[type="password"], input[type="email"], textarea {
    width: 100%;
    padding: 8px 12px;
    font: inherit;
    color: var(--text);
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
  }
  textarea { min-height: 90px; resize: vertical; }
  input:focus, textarea:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-soft); }
  input::placeholder, textarea::placeholder { color: var(--muted); opacity: .7; }

  .choice-row { display: flex; gap: 10px; flex-wrap: wrap; }
  .choice {
    display: flex; align-items: center; gap: 8px;
    padding: 8px 14px; cursor: pointer;
    border: 1px solid var(--border); border-radius: var(--radius);
  }
  .choice:hover { border-color: var(--primary); }
  .choice:has(input:checked) { border-color: var(--primary); background: var(--primary-soft); }
  .choice:focus-within { outline: 2px solid var(--primary); outline-offset: 1px; }
  .choice input { accent-color: var(--primary); }
  .choice-list .choice { margin-bottom: 8px; }

  .info-box {
    padding: 14px 16px;
    background: var(--primary-soft);
    border-left: 4px solid var(--primary);
    border-radius: var(--radius);
  }

  .actions { display: flex; justify-content: flex-end; }
  button[type="submit"] {
    padding: 10px 22px; font: inherit; font-weight: 500; color: #fff;
    background: var(--primary); border: 1px solid var(--primary);
    border-radius: var(--radius); cursor: pointer;
  }
  button[type="submit"]:hover { filter: brightness(1.1); }
  button[type="submit"]:focus-visible { outline: 2px solid var(--primary); outline-offset: 2px; }


  input[type="number"] {
    width: 120px; padding: 8px 12px; font: inherit; color: var(--text);
    background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius);
  }
  input[type="number"]:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-soft); }
  .worker-card {
    border: 1px solid var(--border); border-radius: var(--radius);
    padding: 14px 16px; margin-top: 14px; background: var(--bg);
  }
  .worker-card h3 { font-size: 15px; font-weight: 600; margin-bottom: 12px; }
  .worker-card .form-group:last-child { margin-bottom: 0; }


  @media (max-width: 600px) {
    main { padding: 16px 12px; }
    .card-body { padding: 14px; }
  }
</style>
</head>
<body>

<nav class="navbar">
  <img src="/build/images/RemoteLabz_logo_white.png" alt="">
  <span class="brand">RemoteLabz</span>
</nav>

<header class="jumbotron">
  <div class="inner">
    <h1>Installation de RemoteLabz</h1>
    <p>Choisissez le type d'installation, puis renseignez les paramètres demandés.</p>
  </div>
</header>

<main>
<form id="installerForm" method="post" action="./install.php">

  <!-- Type d'installation -->
  <div class="card">
    <div class="card-header">Type d'installation</div>
    <div class="card-body">
      <div class="type-grid">
        <label class="type-option">
          <input type="radio" name="install_type" value="requirements">
          <span><strong>Requirements only</strong><small>Dépendances système</small></span>
        </label>
        <label class="type-option">
          <input type="radio" name="install_type" value="openvpn">
          <span><strong>Setup OpenVPN only</strong><small>Installation et configuration d'OpenVPN</small></span>
        </label>
        <label class="type-option">
          <input type="radio" name="install_type" value="ssl">
          <span><strong>Configure SSL only</strong><small>Certificats et autorité de certification</small></span>
        </label>
        <label class="type-option">
          <input type="radio" name="install_type" value="remotelabz">
          <span><strong>Install RemoteLabz app only</strong><small>Application RemoteLabz seule</small></span>
        </label>
        <label class="type-option">
          <input type="radio" name="install_type" value="workers">
          <span><strong>Configure worker deployments</strong><small>Déploiement des workers</small></span>
        </label>
        <label class="type-option">
          <input type="radio" name="install_type" value="custom">
          <span><strong>Custom installation</strong><small>Sélection des composants à la carte</small></span>
        </label>
        <label class="type-option">
          <input type="radio" name="install_type" value="auto_install" checked>
          <span><strong>Full automatic installation<span class="badge">Recommandé</span></strong><small>Tout installer et configurer</small></span>
        </label>
      </div>
    </div>
  </div>

  <!-- Paramètres -->
  <div class="card">
    <div class="card-header">Paramètres</div>
    <div class="card-body">

      <!-- Requirements -->
      <div class="conditional-group" id="group-requirements">
        <div class="info-box">
          <strong>Requirements only</strong><br>
          Aucune configuration n'est nécessaire. Les dépendances système seront installées automatiquement.
        </div>
      </div>

      <!-- OpenVPN -->
      <div class="conditional-group" id="group-openvpn">
        <div class="form-group">
          <label class="field-label" for="openvpn_ca_pwd">Mot de passe SSL_CA_KEY<span class="required">*</span></label>
          <input type="password" id="openvpn_ca_pwd" name="openvpn_ssl_ca_key_password" placeholder="••••••••" required>
          <div class="hint">Protection de la clé de l'autorité de certification</div>
        </div>
      </div>

      <!-- SSL -->
      <div class="conditional-group" id="group-ssl">
        <div class="form-group">
          <label class="field-label" for="ssl_ip">IP publique<span class="required">*</span></label>
          <input type="text" id="ssl_ip" name="ssl_ip_public" placeholder="Ex : 203.0.113.10" required>
        </div>
        <div class="form-group">
          <span class="field-label">Architecture serveur<span class="required">*</span></span>
          <div class="choice-row">
            <label class="choice"><input type="radio" name="ssl_server_mode" value="single_server" checked> Single serveur</label>
            <label class="choice"><input type="radio" name="ssl_server_mode" value="multiserver"> Multiserveur</label>
          </div>
        </div>
        <div class="form-group">
          <label class="field-label" for="ssl_mysql">Mot de passe MySQL<span class="required">*</span></label>
          <input type="password" id="ssl_mysql" name="ssl_mysql_password" placeholder="••••••••" required>
        </div>
        <div class="form-group">
          <label class="field-label" for="ssl_ca_pwd">Mot de passe SSL_CA_KEY<span class="required">*</span></label>
          <input type="password" id="ssl_ca_pwd" name="ssl_ssl_ca_key_password" placeholder="••••••••" required>
          <div class="hint">Protection de la clé de l'autorité de certification</div>
        </div>
        <div class="form-group">
          <label class="field-label" for="ssl_mail">Email de contact<span class="required">*</span></label>
          <input type="email" id="ssl_mail" name="ssl_contact_email" placeholder="admin@exemple.com" required>
        </div>
        <div class="form-group">
          <span class="field-label">Certificat auto-signé<span class="required">*</span></span>
          <div class="choice-row">
            <label class="choice"><input type="radio" name="ssl_self_signed" value="yes" checked> Oui</label>
            <label class="choice"><input type="radio" name="ssl_self_signed" value="no"> Non</label>
          </div>
          <div class="hint">Un certificat auto-signé n'est pas reconnu par défaut par les navigateurs</div>
        </div>
      </div>

      <!-- RemoteLabz app -->
      <div class="conditional-group" id="group-remotelabz">
        <div class="form-group">
          <label class="field-label" for="rl_ip">IP publique<span class="required">*</span></label>
          <input type="text" id="rl_ip" name="rl_ip_public" placeholder="Ex : 203.0.113.10" required>
        </div>
        <div class="form-group">
          <span class="field-label">Architecture serveur<span class="required">*</span></span>
          <div class="choice-row">
            <label class="choice"><input type="radio" name="rl_server_mode" value="single_server" checked> Single serveur</label>
            <label class="choice"><input type="radio" name="rl_server_mode" value="multiserver"> Multiserveur</label>
          </div>
        </div>
        <div class="form-group">
          <label class="field-label" for="rl_mysql">Mot de passe MySQL<span class="required">*</span></label>
          <input type="password" id="rl_mysql" name="rl_mysql_password" placeholder="••••••••" required>
        </div>
        <div class="form-group">
          <label class="field-label" for="rl_ca_pwd">Mot de passe SSL_CA_KEY<span class="required">*</span></label>
          <input type="password" id="rl_ca_pwd" name="rl_ssl_ca_key_password" placeholder="••••••••" required>
          <div class="hint">Protection de la clé de l'autorité de certification</div>
        </div>
        <div class="form-group">
          <label class="field-label" for="rl_mail">Email de contact<span class="required">*</span></label>
          <input type="email" id="rl_mail" name="rl_contact_email" placeholder="admin@exemple.com" required>
        </div>
        <div class="form-group">
          <span class="field-label">Certificat auto-signé<span class="required">*</span></span>
          <div class="choice-row">
            <label class="choice"><input type="radio" name="rl_self_signed" value="yes" checked> Oui</label>
            <label class="choice"><input type="radio" name="rl_self_signed" value="no"> Non</label>
          </div>
        </div>
      </div>

      <!-- Workers -->
      <div class="conditional-group" id="group-workers">
        <div class="form-group">
          <label class="field-label" for="wk_front">IP du serveur principal<span class="required">*</span></label>
          <input type="text" id="wk_front" name="workers_front_ip" placeholder="Ex : 203.0.113.10" required>
        </div>
        <div class="form-group">
          <label class="field-label" for="wk_count">Nombre de workers<span class="required">*</span></label>
          <input type="number" id="wk_count" name="workers_count" min="1" max="20" placeholder="1" required>
        </div>
        <div id="workers-list"></div>
      </div>

      <!-- Custom -->
      <div class="conditional-group" id="group-custom">
        <span class="field-label">Composants à installer</span>
        <div class="choice-list">
          <label class="choice"><input type="checkbox" name="custom_requirements" checked> Installer les requirements</label>
          <label class="choice"><input type="checkbox" name="custom_openvpn"> Installer et configurer OpenVPN</label>
          <label class="choice"><input type="checkbox" name="custom_system_params"> Configurer les paramètres système</label>
          <label class="choice"><input type="checkbox" name="custom_ssl" id="customSslCheckbox"> Configurer le SSL</label>

          <div class="sub-conditional" id="sub-ssl-options">
            <span class="field-label">Certificat auto-signé<span class="required">*</span></span>
            <div class="choice-row">
              <label class="choice"><input type="radio" name="custom_ssl_self_signed" value="yes" checked> Oui</label>
              <label class="choice"><input type="radio" name="custom_ssl_self_signed" value="no"> Non</label>
            </div>
          </div>

          <label class="choice"><input type="checkbox" name="custom_remotelabz"> Installer RemoteLabz</label>
          <label class="choice"><input type="checkbox" name="custom_workers"> Configurer le déploiement des workers</label>
        </div>
      </div>

      <!-- Full automatic -->
      <div class="conditional-group" id="group-auto_install">
        <div class="form-group">
          <label class="field-label" for="auto_ip">IP publique<span class="required">*</span></label>
          <input type="text" id="auto_ip" name="ip_public" placeholder="Ex : 203.0.113.10" required>
        </div>
        <div class="form-group">
          <span class="field-label">Architecture serveur<span class="required">*</span></span>
          <div class="choice-row">
            <label class="choice"><input type="radio" name="server_mode" value="single_server" checked> Single serveur</label>
            <label class="choice"><input type="radio" name="server_mode" value="multiserver"> Multiserveur</label>
          </div>
        </div>
        <div class="form-group">
          <label class="field-label" for="auto_mysql">Mot de passe MySQL<span class="required">*</span></label>
          <input type="password" id="auto_mysql" name="mysql_password" placeholder="••••••••" required>
          <div class="hint">Accès à la base de données</div>
        </div>
        <div class="form-group">
          <label class="field-label" for="auto_ca_pwd">Mot de passe SSL_CA_KEY<span class="required">*</span></label>
          <input type="password" id="auto_ca_pwd" name="ssl_ca_key_password" placeholder="••••••••" required>
          <div class="hint">Protection de la clé de l'autorité de certification</div>
        </div>
        <div class="form-group">
          <label class="field-label" for="auto_mail">Email de contact<span class="required">*</span></label>
          <input type="email" id="auto_mail" name="contact_email" placeholder="admin@exemple.com" required>
        </div>
        <div class="form-group">
          <span class="field-label">Certificat auto-signé<span class="required">*</span></span>
          <div class="choice-row">
            <label class="choice"><input type="radio" name="self_signed" value="yes" checked> Oui</label>
            <label class="choice"><input type="radio" name="self_signed" value="no"> Non</label>
          </div>
          <div class="hint">Un certificat auto-signé n'est pas reconnu par défaut par les navigateurs</div>
        </div>
        <div class="form-group">
          <span class="field-label">Déploiement de workers<span class="required">*</span></span>
          <div class="choice-row">
            <label class="choice"><input type="radio" name="deploy_workers" value="yes"> Oui</label>
            <label class="choice"><input type="radio" name="deploy_workers" value="no" checked> Non</label>
          </div>
        </div>
        <div class="sub-conditional" id="sub-auto-workers">
          <div class="form-group">
            <label class="field-label" for="auto_workers_count">Nombre de workers<span class="required">*</span></label>
            <input type="number" id="auto_workers_count" name="auto_workers_count" min="1" max="20" placeholder="1" required>
          </div>
          <div id="auto-workers-list"></div>
        </div>
      </div>

    </div>
  </div>

  <div class="actions">
    <button type="submit">Lancer l'installation</button>
  </div>
</form>
</main>


<script>
  var form = document.getElementById('installerForm');

  // Affiche/masque les groupes et sous-groupes, puis désactive les champs invisibles
  // (ils ne bloquent pas la validation et ne sont pas envoyés au serveur).
  function refresh() {
    var selected = document.querySelector('input[name="install_type"]:checked').value;
    document.querySelectorAll('.conditional-group').forEach(function (g) {
      g.classList.toggle('visible', g.id === 'group-' + selected);
    });
    document.getElementById('sub-ssl-options').classList.toggle('visible',
      document.getElementById('customSslCheckbox').checked);
    document.getElementById('sub-auto-workers').classList.toggle('visible',
      document.querySelector('input[name="deploy_workers"]:checked').value === 'yes');

    form.querySelectorAll('input, textarea').forEach(function (el) {
      if (el.name === 'install_type') return;
      var hidden = false;
      for (var n = el.parentElement; n && n !== form; n = n.parentElement) {
        if ((n.classList.contains('conditional-group') || n.classList.contains('sub-conditional'))
            && !n.classList.contains('visible')) { hidden = true; break; }
      }
      el.disabled = hidden;
    });
  }

  // Génère un bloc de champs par worker selon le nombre saisi
  function setupWorkers(prefix, countId, listId) {
    var count = document.getElementById(countId);
    var list = document.getElementById(listId);

    function card(i) {
      var n = function (f) { return prefix + '[' + i + '][' + f + ']'; };
      var id = function (f) { return prefix + '_' + i + '_' + f; };
      var div = document.createElement('div');
      div.className = 'worker-card';
      div.innerHTML =
        '<h3>Worker ' + (i + 1) + '</h3>' +
        '<div class="form-group"><label class="field-label" for="' + id('user') + '">Compte utilisateur du worker<span class="required">*</span></label>' +
          '<input type="text" id="' + id('user') + '" name="' + n('user') + '" required></div>' +
        '<div class="form-group"><span class="field-label">Topologie<span class="required">*</span></span><div class="choice-row">' +
          '<label class="choice"><input type="radio" name="' + n('topology') + '" value="single_server" checked> Single serveur</label>' +
          '<label class="choice"><input type="radio" name="' + n('topology') + '" value="multiserver"> Multiserveur</label></div></div>' +
        '<div class="form-group"><span class="field-label">VPN hébergé sur le worker<span class="required">*</span></span><div class="choice-row">' +
          '<label class="choice"><input type="radio" name="' + n('vpn') + '" value="yes"> Oui</label>' +
          '<label class="choice"><input type="radio" name="' + n('vpn') + '" value="no" checked> Non</label></div></div>' +
        '<div class="form-group"><label class="field-label" for="' + id('worker_password') + '">Mot de passe du worker<span class="required">*</span></label>' +
          '<input type="password" id="' + id('worker_password') + '" name="' + n('worker_password') + '" placeholder="••••••••" required></div>' +
        '<div class="form-group"><label class="field-label" for="' + id('front_password') + '">Mot de passe du front<span class="required">*</span></label>' +
          '<input type="password" id="' + id('front_password') + '" name="' + n('front_password') + '" placeholder="••••••••" required></div>';
      return div;
    }

    count.addEventListener('input', function () {
      var total = Math.max(0, Math.min(20, parseInt(count.value, 10) || 0));
      while (list.children.length > total) list.removeChild(list.lastChild);
      while (list.children.length < total) list.appendChild(card(list.children.length));
      refresh();
    });
  }

  document.querySelectorAll('input[name="install_type"], input[name="deploy_workers"], #customSslCheckbox')
    .forEach(function (el) { el.addEventListener('change', refresh); });

  setupWorkers('auto_workers', 'auto_workers_count', 'auto-workers-list');
  setupWorkers('workers', 'wk_count', 'workers-list');


  refresh();
</script>
</body>
</html>