<?php
// Ensure errors are visible
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/vendor/autoload.php';

use App\Encryption\Strategy\AesEncryptionStrategy;
use App\Encryption\Strategy\RsaEncryptionStrategy;
use App\Service\EncryptionService;

$encryptionKey = "this-is-a-super-secret-test-key!";

// Make sure keys directory exists
$keysDir = __DIR__ . '/config/jwt';
if (!is_dir($keysDir)) {
    mkdir($keysDir, 0777, true);
}

$privateKeyPath = $keysDir . '/private.pem';
$publicKeyPath = $keysDir . '/public.pem';

// Generate dummy RSA keys if they don't exist
if (!file_exists($privateKeyPath) || !file_exists($publicKeyPath)) {
    $minimalCnf = $keysDir . '/minimal_openssl.cnf';
    $config = array(
        "private_key_bits" => 4096,
        "private_key_type" => OPENSSL_KEYTYPE_RSA,
    );
    if (file_exists($minimalCnf)) {
        $config['config'] = $minimalCnf;
    }

    $res = openssl_pkey_new($config);
    if ($res) {
        openssl_pkey_export($res, $privKey, null, $config);
        file_put_contents($privateKeyPath, $privKey);
        $pubKey = openssl_pkey_get_details($res);
        file_put_contents($publicKeyPath, $pubKey["key"]);
    } else {
        die("<h1>Erreur Critique:</h1><p>Impossible de générer les clés RSA. OpenSSL n'est pas bien configuré sur votre PHP Windows. " . openssl_error_string() . "</p>");
    }
}

// Instantiate Strategies
$aesStrategy = new AesEncryptionStrategy($encryptionKey);
$rsaStrategy = new RsaEncryptionStrategy($publicKeyPath, $privateKeyPath);

$service = new EncryptionService([$aesStrategy, $rsaStrategy]);

$originalData = '';
$encryptedData = '';
$decryptedData = '';
$error = null;
$action = '';
$algorithm = 'aes';
$fileInfo = [];
$currentTab = 'text';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $algorithm = $_POST['algorithm'] ?? 'aes';
    
    if ($action === 'encryptText' && !empty($_POST['data_to_encrypt'])) {
        $currentTab = 'text';
        $originalData = trim($_POST['data_to_encrypt']);
        try {
            $encryptedData = $service->encryptData($originalData, $algorithm);
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }
    } 
    elseif ($action === 'decryptText' && !empty($_POST['encrypted_data'])) {
        $currentTab = 'text';
        $encryptedData = $_POST['encrypted_data'];
        try {
            $decryptedData = $service->decryptData($encryptedData, $algorithm);
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }
    } 
    elseif ($action === 'encryptFile' && isset($_FILES['file_to_encrypt']) && $_FILES['file_to_encrypt']['error'] === UPLOAD_ERR_OK) {
        $currentTab = 'encrypt_file';
        try {
            $uploadDir = __DIR__ . '/uploads';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            
            $originalName = $_FILES['file_to_encrypt']['name'];
            $tmpName = $_FILES['file_to_encrypt']['tmp_name'];
            $fileSize = $_FILES['file_to_encrypt']['size'];
            
            $encPath = $uploadDir . '/' . uniqid() . '-' . $originalName . '.enc';
            
            $service->encryptFile($tmpName, $encPath, $algorithm);
            
            $fileInfo = [
                'name' => $originalName,
                'size' => round($fileSize / 1024, 2) . ' KB',
                'encPath' => str_replace('\\', '/', $encPath),
                'encSize' => round(filesize($encPath) / 1024, 2) . ' KB',
            ];
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }
    }
    elseif ($action === 'decryptFileNow' && !empty($_POST['encPath'])) {
        $currentTab = 'encrypt_file';
        $encPath = $_POST['encPath'];
        $originalName = $_POST['originalName'] ?? 'file.txt';
        try {
            if (!file_exists($encPath)) throw new \Exception("Fichier chiffré introuvable : " . $encPath);
            
            $encryptedContent = file_get_contents($encPath);
            
            // Envoyer au navigateur de l'utilisateur directement la version ENCRYPTÉE
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="encrypted_' . basename($originalName) . '.enc"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . strlen($encryptedContent));
            
            if (ob_get_length()) ob_clean();
            echo $encryptedContent;
            exit;
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }
    }
    elseif ($action === 'uploadDecrypt' && isset($_FILES['file_to_decrypt']) && $_FILES['file_to_decrypt']['error'] === UPLOAD_ERR_OK) {
        $currentTab = 'decrypt_file';
        try {
            $tmpName = $_FILES['file_to_decrypt']['tmp_name'];
            $originalName = $_FILES['file_to_decrypt']['name'];
            
            // Le fichier decrypté s'appellera decrypted_ + le nom original (on enlève .enc si ça se termine par .enc)
            $cleanName = str_replace('.enc', '', basename($originalName));
            
            $encryptedContent = file_get_contents($tmpName);
            $decryptedContent = $service->decryptData($encryptedContent, $algorithm);
            
            // Envoyer au navigateur de l'utilisateur directement
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="decrypted_imported_' . $cleanName . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . strlen($decryptedContent));
            
            if (ob_get_length()) ob_clean();
            echo $decryptedContent;
            exit;
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portail Cryptographique - Fondation Cheikh Zaid</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #0f4c81; /* Bleu Institutionnel */
            --primary-light: #1d70b8;
            --accent: #20b2aa;
            --success: #10b981;
            --bg-color: #f4f7f6;
            --card-bg: rgba(255, 255, 255, 0.95);
            --text-main: #1f2937;
            --text-muted: #6b7280;
            --border: #e5e7eb;
        }

        body { 
            font-family: 'Inter', system-ui, sans-serif; 
            margin: 0; 
            padding: 0; 
            background: linear-gradient(135deg, #eaf0f6 0%, #ffffff 100%);
            color: var(--text-main); 
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        header {
            width: 100%;
            background: white;
            box-shadow: 0 4px 20px rgba(0,0,0,0.03);
            padding: 15px 50px;
            display: flex;
            align-items: center;
            justify-content: flex-start;
            box-sizing: border-box;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .brand-logo {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 26px;
            box-shadow: 0 4px 10px rgba(15, 76, 129, 0.3);
        }

        .brand-logo::after {
            content: "⚕";
        }

        .brand-text h1 {
            margin: 0;
            font-size: 1.3rem;
            color: var(--primary);
            font-weight: 700;
            letter-spacing: -0.3px;
        }

        .brand-text p {
            margin: 0;
            font-size: 0.8rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 1.5px;
            font-weight: 500;
        }

        .container { 
            width: 100%;
            max-width: 850px;
            background: var(--card-bg); 
            backdrop-filter: blur(20px);
            padding: 40px; 
            border-radius: 24px; 
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.04), 0 1px 3px rgba(0,0,0,0.02); 
            margin-top: 50px;
            margin-bottom: 50px;
            border: 1px solid rgba(255,255,255,0.8);
            transition: transform 0.3s ease;
        }

        .page-title {
            text-align: center;
            margin-bottom: 40px;
        }

        .page-title h2 {
            font-size: 1.8rem;
            color: var(--primary);
            margin-bottom: 10px;
        }

        .page-title p {
            color: var(--text-muted);
            font-size: 1.05rem;
        }

        .box { 
            background: #f9fafb; 
            border-left: 4px solid var(--primary); 
            padding: 15px 20px; 
            border-radius: 0 8px 8px 0; 
            margin-bottom: 20px; 
            overflow-wrap: break-word; 
            font-size: 1.05em; 
        }

        .success { 
            color: #047857; 
            background: #d1fae5; 
            border-left-color: var(--success); 
        }

        .error-box {
            background: #fee2e2; 
            border-left-color: #ef4444; 
            color: #b91c1c;
        }

        .crypto-box { 
            font-family: 'Courier New', monospace; 
            font-size: 0.9em; 
            background: #111827; 
            color: #38bdf8; 
            border: none; 
            padding: 20px; 
            border-radius: 12px; 
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.5);
            margin-bottom: 20px;
            line-height: 1.5;
        }

        .tabs { 
            display: flex; 
            gap: 15px; 
            margin-bottom: 30px; 
            justify-content: center;
            border-bottom: 2px solid var(--border);
            padding-bottom: 15px;
        }

        .tab { 
            padding: 12px 25px; 
            border-radius: 8px; 
            cursor: pointer; 
            background: transparent; 
            color: var(--text-muted);
            font-weight: 600;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .tab:hover { 
            color: var(--primary-light);
            background: rgba(29, 112, 184, 0.05);
        }

        .tab.active { 
            background: var(--primary); 
            color: white; 
            box-shadow: 0 4px 12px rgba(15, 76, 129, 0.25);
            transform: translateY(-2px);
        }

        .form-section { 
            display: none; 
            padding: 30px; 
            background: white;
            border: 1px solid var(--border);
            border-radius: 16px; 
            box-shadow: 0 10px 25px rgba(0,0,0,0.02);
            animation: slideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .form-section.active { display: block; }

        @keyframes slideUp { 
            from { opacity: 0; transform: translateY(15px); } 
            to { opacity: 1; transform: translateY(0); } 
        }

        label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: var(--text-main);
            font-size: 0.95rem;
        }

        input[type="text"], select { 
            width: 100%; 
            padding: 14px; 
            font-size: 15px; 
            border: 2px solid var(--border); 
            border-radius: 10px; 
            margin-bottom: 20px;
            box-sizing: border-box;
            transition: border-color 0.2s, box-shadow 0.2s;
            font-family: inherit;
        }

        input[type="text"]:focus, select:focus {
            outline: none;
            border-color: var(--primary-light);
            box-shadow: 0 0 0 4px rgba(29, 112, 184, 0.1);
        }

        input[type="file"] { 
            width: 100%;
            padding: 15px; 
            border: 2px dashed var(--border); 
            border-radius: 10px; 
            background: #f9fafb; 
            margin-bottom: 20px;
            box-sizing: border-box;
            cursor: pointer;
            transition: all 0.2s;
            color: var(--text-muted);
        }
        
        input[type="file"]:hover {
            border-color: var(--primary-light);
            background: #f3f4f6;
        }

        button { 
            padding: 14px 28px; 
            font-size: 15px; 
            font-weight: 600; 
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white; 
            border: none; 
            border-radius: 10px; 
            cursor: pointer; 
            box-shadow: 0 4px 10px rgba(15, 76, 129, 0.25);
            transition: all 0.2s;
            font-family: inherit;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: max-content;
        }

        button:hover { 
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(15, 76, 129, 0.35);
        }

        .btn-green { 
            background: linear-gradient(135deg, var(--success) 0%, #059669 100%);
            box-shadow: 0 4px 10px rgba(16, 185, 129, 0.25);
        }

        .btn-green:hover { 
            box-shadow: 0 6px 15px rgba(16, 185, 129, 0.35);
        }

        hr {
            border: 0;
            height: 1px;
            background: var(--border);
            margin: 30px 0;
        }

        h3 {
            color: var(--text-main);
            font-size: 1.15rem;
            margin-top: 0;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .step-badge {
            background: var(--primary-light);
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <header>
        <div class="brand">
            <div class="brand-logo" title="Logo Médical UIASS"></div>
            <div class="brand-text">
                <h1>Fondation Cheikh Zaid</h1>
                <p>Système de Gestion Hospitalière</p>
            </div>
        </div>
    </header>

    <div class="container">
        <div class="page-title">
            <h2>Module de Chiffrement Sécurisé</h2>
            <p>Protection des données sensibles et dossiers médicaux (Standard AES-256 / RSA-4096)</p>
        </div>
        
        <div class="tabs">
            <div class="tab <?= $currentTab === 'text' ? 'active' : '' ?>" onclick="switchTab('text')">📄 Texte</div>
            <div class="tab <?= $currentTab === 'encrypt_file' ? 'active' : '' ?>" onclick="switchTab('encrypt_file')">🔒 Crypter un Fichier</div>
            <div class="tab <?= $currentTab === 'decrypt_file' ? 'active' : '' ?>" onclick="switchTab('decrypt_file')">🔓 Décrypter un Fichier</div>
        </div>

        <div id="section_text" class="form-section <?= $currentTab === 'text' ? 'active' : '' ?>">
            <form method="POST" action="">
                <input type="hidden" name="action" value="encryptText">
                <div class="algo-selector">
                    <label>Algorithme de Sécurité :</label>
                    <select name="algorithm">
                        <option value="aes" <?= $algorithm === 'aes' ? 'selected' : '' ?>>AES-256-GCM (Symétrique - Recommandé)</option>
                        <option value="rsa" <?= $algorithm === 'rsa' ? 'selected' : '' ?>>RSA-4096 Hybride (Asymétrique)</option>
                    </select>
                </div>
                <h3><span class="step-badge">1</span> Saisir les données à crypter</h3>
                <input type="text" name="data_to_encrypt" placeholder="Ex: Informations confidentielles du patient..." value="<?= htmlspecialchars($originalData) ?>" required>
                <button type="submit">🔒 Lancer le chiffrement</button>
            </form>

            <?php if ($action === 'encryptText' && $encryptedData !== '' && !$error): ?>
                <hr>
                <h3>Résultat de Sécurisation (<?= strtoupper($algorithm) ?>)</h3>
                <div class="crypto-box" style="word-break: break-all;"><?= htmlspecialchars($encryptedData) ?></div>
                
                <form method="POST" action="">
                    <input type="hidden" name="action" value="decryptText">
                    <input type="hidden" name="algorithm" value="<?= $algorithm ?>">
                    <input type="hidden" name="encrypted_data" value="<?= htmlspecialchars($encryptedData) ?>">
                    <h3><span class="step-badge">2</span> Vérification Inversée</h3>
                    <p style="color: var(--text-muted); font-size: 0.95rem;">Assurez-vous que l'extraction des données fonctionne correctement depuis la base.</p>
                    <button type="submit" class="btn-green">🔓 Décrypter la donnée</button>
                </form>
            <?php elseif ($action === 'decryptText' && $decryptedData !== '' && !$error): ?>
                <hr>
                <h3>Donnée Décryptée avec Succès :</h3>
                <div class="box success">✅ <span><?= htmlspecialchars($decryptedData) ?></span></div>
            <?php endif; ?>
        </div>

        <div id="section_encrypt_file" class="form-section <?= $currentTab === 'encrypt_file' ? 'active' : '' ?>">
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="action" value="encryptFile">
                <div class="algo-selector">
                    <label>Algorithme de Sécurité pour les Fichiers :</label>
                    <select name="algorithm">
                        <option value="aes" <?= $algorithm === 'aes' ? 'selected' : '' ?>>AES-256-GCM (Haute Performance)</option>
                        <option value="rsa" <?= $algorithm === 'rsa' ? 'selected' : '' ?>>RSA-4096 Hybride (Enveloppe Numérique)</option>
                    </select>
                </div>
                <h3><span class="step-badge">1</span> Uploader un fichier médical</h3>
                <input type="file" name="file_to_encrypt" required>
                <button type="submit">🔒 Chiffrer le document</button>
            </form>

            <?php if ($action === 'encryptFile' && !empty($fileInfo) && !$error): ?>
                <hr>
                <h3>Document Sécurisé et Archivé (<?= strtoupper($algorithm) ?>)</h3>
                <div class="crypto-box">
                    <strong>Fichier généré :</strong> <?= htmlspecialchars(basename($fileInfo['encPath'])) ?><br><br>
                    <strong>Chemin d'archivage :</strong> <?= htmlspecialchars($fileInfo['encPath']) ?><br><br>
                    <strong>Taille sur disque :</strong> <?= $fileInfo['encSize'] ?> 
                </div>
                
                <form method="POST" action="">
                    <input type="hidden" name="action" value="decryptFileNow">
                    <input type="hidden" name="algorithm" value="<?= $algorithm ?>">
                    <input type="hidden" name="encPath" value="<?= htmlspecialchars($fileInfo['encPath']) ?>">
                    <input type="hidden" name="originalName" value="<?= htmlspecialchars($fileInfo['name']) ?>">
                    <button type="submit" class="btn-green">⬇️ Télécharger le fichier encrypté</button>
                </form>
            <?php endif; ?>
        </div>

        <div id="section_decrypt_file" class="form-section <?= $currentTab === 'decrypt_file' ? 'active' : '' ?>">
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="action" value="uploadDecrypt">
                <div class="algo-selector">
                    <label>Protocole de déchiffrement requis :</label>
                    <select name="algorithm">
                        <option value="aes" <?= $algorithm === 'aes' ? 'selected' : '' ?>>AES-256-GCM</option>
                        <option value="rsa" <?= $algorithm === 'rsa' ? 'selected' : '' ?>>RSA-4096 Hybride</option>
                    </select>
                </div>
                <h3>Déchiffrement d'Archive (.enc)</h3>
                <input type="file" name="file_to_decrypt" accept=".enc,.txt,.bin,.*" required>
                <button type="submit" class="btn-green">🔓 Scanner et Restaurer l'Original</button>
            </form>
        </div>

        <?php if ($error): ?>
            <hr>
            <h3>Alerte Système</h3>
            <div class="box error-box">🚨 <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
    </div>

    <script>
        function disableAllRequired() {
            document.querySelectorAll('input[type="file"], input[type="text"]').forEach(el => {
                el.removeAttribute('required');
            });
        }

        function switchTab(mode) {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.form-section').forEach(s => s.classList.remove('active'));
            disableAllRequired();
            
            if (mode === 'text') {
                document.querySelectorAll('.tab')[0].classList.add('active');
                document.getElementById('section_text').classList.add('active');
                document.querySelector('input[name="data_to_encrypt"]').setAttribute('required', 'required');
            } else if (mode === 'encrypt_file') {
                document.querySelectorAll('.tab')[1].classList.add('active');
                document.getElementById('section_encrypt_file').classList.add('active');
                document.querySelector('input[name="file_to_encrypt"]').setAttribute('required', 'required');
            } else if (mode === 'decrypt_file') {
                document.querySelectorAll('.tab')[2].classList.add('active');
                document.getElementById('section_decrypt_file').classList.add('active');
                document.querySelector('input[name="file_to_decrypt"]').setAttribute('required', 'required');
            }
        }
        
        switchTab('<?= $currentTab ?>');
    </script>
</body>
</html>
