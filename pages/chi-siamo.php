<?php

$pageTitle = 'Chi siamo - StartUpFlow';
$currentPage = 'chi-siamo';

$additionalCss = '<link rel="stylesheet" href="/assets/css/chi-siamo.css">';

require_once __DIR__ . '/../includes/layout/header.php';

?>

	<main class="main">
		<div class="page-content">
			<h1>Chi siamo</h1>
			
			<p>
				StartupFlow è una piattaforma innovativa progettata per semplificare la gestione finanziaria e amministrativa delle startup e delle piccole imprese.
				Nati dall'esigenza di automatizzare processi spesso complessi e dispendiosi, offriamo soluzioni intuitive che permettono agli imprenditori di concentrarsi su ciò che conta davvero: far crescere il proprio business.
			</p>
			
			<h2>La nostra missione</h2>
			<p>
				Vogliamo democraticizzare l'accesso a strumenti professionali di gestione aziendale, rendendoli accessibili a tutti, indipendentemente dalle dimensioni o dal settore di appartenenza. 
				Crediamo che ogni impresa meriti di avere il controllo completo sulla propria salute finanziaria, senza dover investire in soluzioni costose e complesse.
			</p>
			
			<h2>Il nostro team</h2>
			<div class="team-grid">
				<div class="team-card">
					<img src="https://placehold.co/120x120" alt="Mario Rossi">
					<h3>Mario Rossi</h3>
					<p>CEO & Founder</p>
				</div>
				<div class="team-card">
					<img src="https://placehold.co/120x120" alt="Luisa Bianchi">
					<h3>Luisa Bianchi</h3>
					<p>CTO</p>
				</div>
				<div class="team-card">
					<img src="https://placehold.co/120x120" alt="Marco Verdi">
					<h3>Marco Verdi</h3>
					<p>Lead Developer</p>
				</div>
				<div class="team-card">
					<img src="https://placehold.co/120x120" alt="Sara Neri">
					<h3>Sara Neri</h3>
					<p>Product Manager</p>
				</div>
			</div>
			
			<a href="/" class="back-link">← Torna alla Dashboard</a>
		</div>
	</main>
	
<?php
require_once __DIR__ . '/../includes/layout/footer.php';
