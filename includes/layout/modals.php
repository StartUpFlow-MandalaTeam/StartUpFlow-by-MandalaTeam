	<dialog class="profile-modal" id="profile-modal" aria-labelledby="profile-modal-title">
		<form class="profile-modal__panel" method="dialog" id="profile-form" enctype="multipart/form-data">
			<?php echo csrfInput(); ?>
			<header class="profile-modal__header">
				<div>
					<p class="profile-modal__eyebrow">Dati profilo</p>
					<h2 id="profile-modal-title">Anagrafica utente</h2>
				</div>
				<button type="button" class="profile-modal__close" data-profile-modal-close aria-label="Chiudi modale">
					<span class="material-symbols-outlined" aria-hidden="true">close</span>
				</button>
			</header>

			<div class="profile-modal__grid">
				<label class="profile-modal__field">
					<span>Username</span>
					<input type="text" value="<?php echo $username; ?>" readonly>
				</label>
				<label class="profile-modal__field">
					<span>Email</span>
					<input type="email" value="<?php echo $email ?? ''; ?>" readonly>
				</label>
				<label class="profile-modal__field">
					<span>Data di creazione</span>
					<input type="text" value="<?php echo $createdAt ?? ''; ?>" readonly>
				</label>
				<label class="profile-modal__field">
					<span>Immagine avatar</span>
					<input type="file" name="avatar" accept="image/jpeg,image/png,image/webp,image/gif">
				</label>
				<div class="profile-modal__divider" aria-hidden="true"></div>
				<label class="profile-modal__field">
					<span>Nome</span>
					<input type="text" name="nome" value="" placeholder="Inserisci il nome">
				</label>
				<label class="profile-modal__field">
					<span>Cognome</span>
					<input type="text" name="cognome" value="" placeholder="Inserisci il cognome">
				</label>
				<label class="profile-modal__field">
					<span>Data di nascita</span>
					<input type="date" name="data_di_nascita" value="">
				</label>
				<label class="profile-modal__field">
					<span>Luogo di nascita</span>
					<input type="text" name="luogo_di_nascita" value="" placeholder="Inserisci il luogo di nascita">
				</label>
				<label class="profile-modal__field">
					<span>Stato di nascita</span>
					<select name="stato" id="stato_nascita" aria-label="Stato di nascita" data-countries-select>
						<option value="" selected>Seleziona uno Stato</option>
					</select>
				</label>
				<label class="profile-modal__field">
					<span>Codice fiscale</span>
					<input type="text" name="codice_fiscale" value="" placeholder="Inserisci il codice fiscale">
				</label>
				<label class="profile-modal__field profile-modal__field--full">
					<span>Indirizzo</span>
					<input type="text" name="indirizzo" value="" placeholder="Inserisci l'indirizzo">
				</label>
				<label class="profile-modal__field">
					<span>Città</span>
					<input type="text" name="citta" value="" placeholder="Inserisci la città">
				</label>
				<label class="profile-modal__field">
					<span>CAP</span>
					<input type="text" name="cap" value="" placeholder="Inserisci il CAP" inputmode="numeric" maxlength="5">
				</label>
			</div>

			<footer class="profile-modal__actions">
				<button type="button" class="profile-modal__save">Salva</button>
				<button type="button" class="profile-modal__change-email" data-change-email-open>Modifica email</button>
			</footer>
		</form>
	</dialog>

	<dialog class="change-email-modal" id="change-email-modal" aria-labelledby="change-email-title">
		<form class="change-email-panel" method="post" action="/auth/process-change-email.php" id="change-email-form">
			<header class="change-email-header">
				<div>
					<h2 id="change-email-title">Cambia email</h2>
				</div>
			</header>

			<div class="change-email-body">
				<?php echo csrfInput(); ?>
				<label class="profile-modal__field">
					<span>Nuova email</span>
					<input type="email" name="new_email" required autocomplete="email">
				</label>

				<label class="profile-modal__field">
					<span>Password corrente</span>
					<input type="password" name="current_password" required autocomplete="current-password">
				</label>

				<p class="hint">Ti invieremo un link di verifica alla nuova email. Conferma entro 24 ore.</p>
			</div>

			<footer class="change-email-actions">
				<button type="submit" class="profile-modal__save">Invia verifica</button>
				<button type="button" class="profile-modal__cancel" data-change-email-cancel>Annulla</button>
			</footer>
		</form>
	</dialog>

	<!-- Popup Modal per la Legenda Stati CRM -->
	<div id="statusPopupModal" class="status-modal" style="display:none;">
		<div class="status-modal-content">
			<h3 id="popupTitle"></h3>
			<p id="popupDesc"></p>
			<button onclick="closeStatusPopup()" class="btn btn-primary">Chiudi</button>
		</div>
	</div>

	<script>
	window.showStatusPopup = function(title, desc) {
		document.getElementById('popupTitle').innerText = title;
		document.getElementById('popupDesc').innerText = desc;
		document.getElementById('statusPopupModal').style.display = 'flex';
	};
	window.closeStatusPopup = function() {
		document.getElementById('statusPopupModal').style.display = 'none';
	};
	</script>

	<dialog class="profile-modal" id="order-modal" aria-labelledby="order-modal-title">
		<form class="profile-modal__panel" method="POST" action="crm-clienti.php?action=store_order" id="order-form">
			<?php echo function_exists('csrfInput') ? csrfInput() : ''; ?>
			<header class="profile-modal__header">
				<div>
					<p class="profile-modal__eyebrow">Gestione Ordini</p>
					<h2 id="order-modal-title">Nuovo Ordine</h2>
				</div>
				<button type="button" class="profile-modal__close" data-order-modal-close aria-label="Chiudi modale">
					<span class="material-symbols-outlined" aria-hidden="true">close</span>
				</button>
			</header>

			<div class="profile-modal__grid">
				<label class="profile-modal__field">
					<span>Numero Ordine</span>
					<input type="text" name="order_number" class="form-control" placeholder="N. Ordine" required>
				</label>
				<label class="profile-modal__field">
					<span>Totale (€)</span>
					<input type="number" name="total" class="form-control" placeholder="0.00" step="0.01" min="0">
				</label>
				<label class="profile-modal__field">
					<span>Valuta</span>
					<select name="currency" class="form-control">
						<option value="EUR">EUR</option>
						<option value="USD">USD</option>
						<option value="GBP">GBP</option>
					</select>
				</label>
				<label class="profile-modal__field">
					<span>Scadenza</span>
					<input type="date" name="due_date" class="form-control">
				</label>
				<label class="profile-modal__field">
					<span>Regime fiscale</span>
					<select name="tax_regime" class="form-control">
						<option value="forfettario">Forfettario</option>
						<option value="ordinario">Ordinario</option>
					</select>
				</label>
			</div>

			<input type="hidden" name="client_id" id="order_client_id" value="">

			<footer class="profile-modal__actions">
				<button type="submit" class="profile-modal__save">Crea Ordine</button>
				<button type="button" class="profile-modal__cancel" data-order-modal-close>Annulla</button>
			</footer>
		</form>
	</dialog>

	<script>
	document.addEventListener('click', (event) => {
		const openBtn = event.target.closest('[data-order-modal-open]');
		const closeBtn = event.target.closest('[data-order-modal-close]');
		const orderModal = document.getElementById('order-modal');
		const orderClientIdInput = document.getElementById('order_client_id');

		if (openBtn) {
			if (orderClientIdInput && openBtn.dataset.clientId) {
				orderClientIdInput.value = openBtn.dataset.clientId;
			}
			if (orderModal && !orderModal.open) {
				orderModal.showModal();
			}
		}

		if (closeBtn) {
			if (orderModal && orderModal.open) {
				orderModal.close();
			}
		}

		if (orderModal && event.target === orderModal) {
			orderModal.close();
		}
	});

	document.addEventListener('submit', (event) => {
		const form = event.target;
		if (form && form.id === 'order-form') {
			setTimeout(() => {
				form.reset();
				const modal = document.getElementById('order-modal');
				if (modal && modal.open) modal.close();
			}, 300);
		}
	});
	</script>























































































































































































































































































































































































































