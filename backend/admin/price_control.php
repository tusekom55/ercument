<?php
/**
 * Admin Panel - Fiyat Kontrol Sistemi
 */

session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils/price_manager.php';

// Admin kontrolü - Geçici olarak devre dışı (test için)
// if (!isset($_SESSION['admin_id'])) {
//     header('Location: login.php');
//     exit;
// }

$priceManager = new PriceManager();
$message = '';
$error = '';

// Fiyat artırma işlemi
if (isset($_POST['action']) && $_POST['action'] === 'increase_price' && isset($_POST['coin_code'], $_POST['increase_percent'])) {
    $coin_code = trim($_POST['coin_code']);
    $increase_percent = floatval($_POST['increase_percent']);
    
    if ($increase_percent > 0 && $increase_percent <= 1000) {
        $result = $priceManager->increasePriceByPercent($coin_code, $increase_percent);
        
        if ($result['success']) {
            $message = $result['message'] . " (₺" . number_format($result['old_price'], 2) . " → ₺" . number_format($result['new_price'], 2) . ")";
        } else {
            $error = $result['message'];
        }
    } else {
        $error = "Geçersiz artış oranı. 0-1000 arasında olmalıdır.";
    }
}

// Manuel fiyat güncelleme
if (isset($_POST['action']) && $_POST['action'] === 'update_manual_prices') {
    $priceManager->updateAllPrices();
    $message = "Tüm fiyatlar güncellendi (API + Manuel dalgalanma)";
}

// Coin listesini al
try {
    $conn = db_connect();
    $sql = "SELECT id, coin_adi, coin_kodu, current_price, price_change_24h, price_source, last_update 
            FROM coins 
            WHERE is_active = 1 
            ORDER BY 
                CASE 
                    WHEN coin_kodu IN ('T', 'SEX', 'TTT') THEN 1 
                    ELSE 2 
                END, coin_adi";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $coins = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = "Veritabanı hatası: " . $e->getMessage();
    $coins = [];
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fiyat Kontrol - Admin Panel</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #eee;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
        }
        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-warning { background: #ffc107; color: black; }
        .btn-danger { background: #dc3545; color: white; }
        .btn:hover { opacity: 0.8; }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        .price-controls {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        .control-box {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }
        .control-box h3 {
            margin-top: 0;
            color: #495057;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .coins-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .coins-table th,
        .coins-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        .coins-table th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        .coins-table tr:hover {
            background-color: #f5f5f5;
        }
        
        .price-source {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        .source-api { background: #d1ecf1; color: #0c5460; }
        .source-manual { background: #fff3cd; color: #856404; }
        .source-admin { background: #d4edda; color: #155724; }
        
        .price-change {
            font-weight: bold;
        }
        .positive { color: #28a745; }
        .negative { color: #dc3545; }
        
        .quick-actions {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎯 Fiyat Kontrol Sistemi</h1>
            <div>
                <a href="coins.php" class="btn btn-primary">Coin Yönetimi</a>
                <a href="../admin-panel.html" class="btn btn-secondary">Ana Panel</a>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success">✅ <?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger">❌ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="quick-actions">
            <form method="post" style="display: inline;">
                <input type="hidden" name="action" value="update_manual_prices">
                <button type="submit" class="btn btn-success">🔄 Tüm Fiyatları Güncelle</button>
            </form>
            <a href="?update_prices=1" class="btn btn-warning">⚡ API Fiyatlarını Çek</a>
        </div>

        <div class="price-controls">
            <div class="control-box">
                <h3>📈 Manuel Fiyat Artırma</h3>
                <form method="post">
                    <input type="hidden" name="action" value="increase_price">
                    
                    <div class="form-group">
                        <label>Coin Seç:</label>
                        <select name="coin_code" class="form-control" required>
                            <option value="">Coin Seçin...</option>
                            <?php foreach ($coins as $coin): ?>
                                <?php if (in_array($coin['coin_kodu'], ['T', 'SEX', 'TTT'])): ?>
                                    <option value="<?= $coin['coin_kodu'] ?>">
                                        <?= $coin['coin_adi'] ?> (<?= $coin['coin_kodu'] ?>) - ₺<?= number_format($coin['current_price'], 2) ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Artış Oranı (%):</label>
                        <input type="number" name="increase_percent" class="form-control" 
                               min="1" max="1000" step="0.1" placeholder="Örn: 50" required>
                        <small>1-1000 arasında değer girin (50 = %50 artış)</small>
                    </div>
                    
                    <button type="submit" class="btn btn-danger">🚀 Fiyatı Artır</button>
                </form>
            </div>

            <div class="control-box">
                <h3>ℹ️ Sistem Bilgileri</h3>
                <p><strong>API Coinleri:</strong> BTC, ETH, BNB, XRP, USDT, ADA, SOL, DOGE, MATIC, DOT</p>
                <p><strong>Manuel Coinler:</strong> T (Tugaycoin), SEX, TTT (Dolar)</p>
                <p><strong>Dalgalanma Aralığı:</strong> -%5 ile +%30</p>
                <p><strong>Güncelleme Sıklığı:</strong> Her 5 dakika (Cron Job)</p>
                
                <div style="margin-top: 15px;">
                    <strong>Son Güncelleme:</strong><br>
                    <span id="lastUpdate"><?= date('d.m.Y H:i:s') ?></span>
                </div>
            </div>
        </div>

        <h2>💰 Coin Fiyat Listesi</h2>
        <table class="coins-table">
            <thead>
                <tr>
                    <th>Coin</th>
                    <th>Kod</th>
                    <th>Güncel Fiyat</th>
                    <th>24s Değişim</th>
                    <th>Kaynak</th>
                    <th>Son Güncelleme</th>
                    <th>İşlemler</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($coins as $coin): ?>
                    <tr>
                        <td><?= htmlspecialchars($coin['coin_adi']) ?></td>
                        <td><strong><?= htmlspecialchars($coin['coin_kodu']) ?></strong></td>
                        <td>₺<?= number_format($coin['current_price'], 2) ?></td>
                        <td>
                            <?php 
                            $change = floatval($coin['price_change_24h']);
                            $class = $change >= 0 ? 'positive' : 'negative';
                            $sign = $change >= 0 ? '+' : '';
                            ?>
                            <span class="price-change <?= $class ?>">
                                <?= $sign ?><?= number_format($change, 2) ?>%
                            </span>
                        </td>
                        <td>
                            <?php 
                            $source = $coin['price_source'] ?? 'manual';
                            $sourceClass = 'source-' . $source;
                            $sourceText = [
                                'api' => 'API',
                                'manual' => 'Manuel',
                                'admin' => 'Admin'
                            ][$source] ?? 'Manuel';
                            ?>
                            <span class="price-source <?= $sourceClass ?>"><?= $sourceText ?></span>
                        </td>
                        <td>
                            <?= $coin['last_update'] ? date('d.m.Y H:i', strtotime($coin['last_update'])) : 'Bilinmiyor' ?>
                        </td>
                        <td>
                            <?php if (in_array($coin['coin_kodu'], ['T', 'SEX', 'TTT'])): ?>
                                <button onclick="quickIncrease('<?= $coin['coin_kodu'] ?>', 50)" class="btn btn-warning btn-sm">+50%</button>
                                <button onclick="quickIncrease('<?= $coin['coin_kodu'] ?>', 100)" class="btn btn-danger btn-sm">+100%</button>
                            <?php else: ?>
                                <span style="color: #6c757d;">API Coin</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <script>
        function quickIncrease(coinCode, percent) {
            if (confirm(`${coinCode} coin fiyatını %${percent} artırmak istediğinizden emin misiniz?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="increase_price">
                    <input type="hidden" name="coin_code" value="${coinCode}">
                    <input type="hidden" name="increase_percent" value="${percent}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Sayfa yenilenme zamanlayıcısı
        setInterval(() => {
            document.getElementById('lastUpdate').textContent = new Date().toLocaleString('tr-TR');
        }, 60000);
    </script>
</body>
</html>
