<?php
//Dati recuperabili del database
<?php
//Dati recuperabili del database
$cassaIniziale=50000; 
$entrateMensili=2000;
$speseMensili=3000; 

$netBurnrate=$speseMensili-$entrateMensili;
$runwayMesi= $netBurnrate> 0 ? round($cassaIniziale/$netBurnrate, 1) : INF;
// Calcolo percentuale per la barra indicatore
$sogliaSicurezzaMesi = 12;
$percentualeSalute = min(100, max(0, ($runwayMesi / $sogliaSicurezzaMesi) * 100));


?>

<div class="burn-rate-card">
    <h2>Burnrate & Runway Meter</h2>
    <div class="metrics-grid">
    <div class="metric">
<span class="label">Cassa attuale</span>
<span class="value">€ <?php echo number_format($cassaIniziale, 2, ',', '.'); ?></span>
<div class="metric">

<span class="label">Net Burn Rate</span>
<span class="value text">-€ <?php echo number_format($netBurnrate, 2, ',', '.'); ?></span>
<div class="metric">
    <span class="label">Autonomia (Runway)</span>
    <?php echo is_infinite($runwayMesi) ? 'Infinito' : $runwayMesi . ' mesi'; ?>

</div>
</div>    
</div>
    </div>
</div>
<!--Barra indicatore (meter)-->
<div class="meter-container">
    <div class="meter-label"></div>
    <div class="meter-bar-bg"></div>
    <div class="meter-bar-fill <?php echo $percentualeSalute; ?>"style="width: <?php echo $percentualeSalute; ?>%">
    </div>
</div>
<style>
    burn-rate-card {
        background:#fff;
        border-radius:8px;
        padding: 1.5rem;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        .metrics.grid{
            display: flex; justify-content: space-between; margin-bottom:1.5 rem; gap:1rem;
        }
        .metric{ display:flex; flex-direction:column;}
        .metric .label{font: size 0.85rem; color:#666;}
        .metric .value{font: size 1.4rem; font-weight:bold; color:blanchedalmond}
        .success{color: #16a34a};
        }
       /*Stili meter*/
       .meter.bar.bg{ width: 100%; height:16px; background-color:#e5e7eb; border-radius:8px;overflow:hidden; margin-top:0.5rem;}
       .meter-bar-fill{ height:100%; transition:width 0.5s ease-in-out}
       .meter-bar-fill-success{background-color:#22c55e;};
       .
    
</style>


    </header>
</div>

