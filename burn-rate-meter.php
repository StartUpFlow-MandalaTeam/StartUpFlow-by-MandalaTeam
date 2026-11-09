<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (!isLoggedIn()) {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        http_response_code(401);
        exit;
    }
    header('Location: /auth/login.php');
    exit;
}

$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
if (!$isAjax) {
    header('Location: /');
    exit;
}

$username = $_SESSION['username'] ?? 'Utente';
$cassaIniziale = 50000;
$entrateMensili = 2000;
$speseMensili = 3000;
$netBurnRate = $speseMensili - $entrateMensili;
$runwayMesi = $netBurnRate > 0 ? round($cassaIniziale / $netBurnRate, 1) : INF;
$sogliaSicurezzaMesi = 12;
$percentualeSalute = min(100, max(0, ($runwayMesi / $sogliaSicurezzaMesi) * 100));
?>

<div class="service-page">
    <header class="service-header">
        <a href="/" data-spa="true" class="back-link">← Torna alla Dashboard</a>
        <h1>Burn Rate Meter</h1>
        <p>Benvenuto, <?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>!</p>
    </header>

    <div class="service-content">
        <section class="burn-rate-card">
            <h2>Burn Rate &amp; Runway</h2>
            <div class="metrics-grid">
                <div class="metric">
                    <span class="label">Cassa attuale</span>
                    <span class="value">€ <?php echo number_format($cassaIniziale, 2, ',', '.'); ?></span>
                </div>
                <div class="metric">
                    <span class="label">Net Burn Rate mensile</span>
                    <span class="value">€ <?php echo number_format($netBurnRate, 2, ',', '.'); ?></span>
                </div>
                <div class="metric">
                    <span class="label">Autonomia (Runway)</span>
                    <span class="value"><?php echo is_infinite($runwayMesi) ? 'Infinito' : $runwayMesi . ' mesi'; ?></span>
                </div>
            </div>

            <div class="meter-container">
                <div class="meter-label">Salute finanziaria</div>
                <div
                    class="meter-bar-bg"
                    role="progressbar"
                    aria-label="Mesi di autonomia rispetto alla soglia di sicurezza"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    aria-valuenow="<?php echo (int) round($percentualeSalute); ?>"
                >
                    <div class="meter-bar-fill" style="width: <?php echo $percentualeSalute; ?>%"></div>
                </div>
            </div>
        </section>
    </div>
</div>
