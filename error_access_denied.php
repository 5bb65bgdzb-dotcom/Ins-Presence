<?php
/**
 * Page d'erreur d'accès refusé
 */

require_once '../config.php';

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accès Refusé - <?php echo SITE_NAME; ?></title>
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
            align-items: center;
            justify-content: center;
            color: #333;
        }
        
        .container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            padding: 50px;
            text-align: center;
            max-width: 500px;
            animation: slideIn 0.5s ease-out;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .icon {
            font-size: 80px;
            margin-bottom: 20px;
            animation: bounce 2s infinite;
        }
        
        @keyframes bounce {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-10px);
            }
        }
        
        h1 {
            color: #e74c3c;
            font-size: 32px;
            margin-bottom: 15px;
        }
        
        .error-code {
            font-size: 14px;
            color: #95a5a6;
            margin-bottom: 20px;
            font-family: 'Courier New', monospace;
        }
        
        p {
            color: #666;
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        
        .info-box {
            background: #f8f9fa;
            border-left: 4px solid #e74c3c;
            padding: 15px;
            text-align: left;
            margin-bottom: 30px;
            border-radius: 5px;
        }
        
        .info-box strong {
            color: #2c3e50;
        }
        
        .button-group {
            display: flex;
            gap: 15px;
            justify-content: center;
        }
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 15px;
            text-decoration: none;
            transition: all 0.3s;
            display: inline-block;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #ecf0f1;
            color: #2c3e50;
            border: 2px solid #bdc3c7;
        }
        
        .btn-secondary:hover {
            background: #bdc3c7;
            color: white;
        }
        
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ecf0f1;
            font-size: 12px;
            color: #95a5a6;
        }
        
        .footer a {
            color: #667eea;
            text-decoration: none;
        }
        
        .footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">🔒</div>
        
        <h1>Accès Refusé</h1>
        <div class="error-code">Erreur 403 - Forbidden</div>
        
        <p>Vous n'avez pas les permissions nécessaires pour accéder à cette ressource.</p>
        
        <div class="info-box">
            <strong>ℹ️ Information</strong><br>
            Votre compte n'a pas les droits d'accès pour cette action. 
            Si vous pensez que c'est une erreur, contactez l'administrateur.
        </div>
        
        <div class="button-group">
            <a href="connexion.php?logout=1" class="btn btn-primary">🔄 Se Déconnecter</a>
            <a href="javascript:history.back()" class="btn btn-secondary">← Retour</a>
        </div>
        
        <div class="footer">
            <p><?php echo SITE_NAME; ?> © 2026</p>
            <p style="margin-top: 10px; font-size: 11px;">Redirection automatique en <span id="countdown">10</span> secondes...</p>
        </div>
    </div>
    
    <script>
        // Redirection automatique vers la connexion après 10 secondes
        let count = 10;
        setInterval(function() {
            document.getElementById('countdown').textContent = --count;
            if (count <= 0) {
                window.location.href = 'connexion.php?logout=1';
            }
        }, 1000);
    </script>
</body>
</html>
