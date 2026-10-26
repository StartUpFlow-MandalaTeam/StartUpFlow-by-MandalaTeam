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
					<input type="text" name="nome" value="<?php echo $firstName ?? ''; ?>" placeholder="Inserisci il nome" required>
				</label>
				<label class="profile-modal__field">
					<span>Cognome</span>
					<input type="text" name="cognome" value="<?php echo $lastName ?? ''; ?>" placeholder="Inserisci il cognome" required>
				</label>
				<label class="profile-modal__field">
					<span>Data di nascita</span>
					<input type="date" name="data_di_nascita" value="<?php echo $birthDate ?? ''; ?>" required>
				</label>
				<label class="profile-modal__field">
					<span>Luogo di nascita</span>
					<input type="text" name="luogo_di_nascita" value="<?php echo $birthPlace ?? ''; ?>" placeholder="Inserisci il luogo di nascita" required>
				</label>
				<label class="profile-modal__field">
					<span>Stato di nascita</span>
					<select name="stato" id="stato_nascita" aria-label="Stato di nascita" data-countries-select data-selected-country="<?php echo $birthCountry ?? ''; ?>" required>
						<option value="<?php echo $birthCountry ?? ''; ?>" selected>Seleziona uno Stato</option>
					</select>
				</label>
				<label class="profile-modal__field">
					<span>Codice fiscale</span>
					<input type="text" name="codice_fiscale" value="<?php echo $cf ?? ''; ?>" placeholder="Inserisci il codice fiscale" maxlength="16" required>
				</label>
				<label class="profile-modal__field profile-modal__field--full">
					<span>Indirizzo</span>
					<input type="text" name="indirizzo" value="<?php echo $address ?? ''; ?>" placeholder="Inserisci l'indirizzo" required>
				</label>
				<label class="profile-modal__field">
					<span>Città</span>
					<input type="text" name="citta" value="<?php echo $city ?? ''; ?>" placeholder="Inserisci la città" required>
				</label>
				<label class="profile-modal__field">
					<span>CAP</span>
					<input type="text" name="cap" value="<?php echo $postalCode ?? ''; ?>" placeholder="Inserisci il CAP" inputmode="numeric" maxlength="5" required>
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
					<input type="text" name="order_number" class="form-control" placeholder="Es. ORD-12345" required>
				</label>
				<label class="profile-modal__field">
					<span>Valuta</span>
					<select name="currency" class="form-control">
						<option value="EUR">EUR</option>
						<option value="USD">USD</option>
						<option value="GBP">GBP</option>
					</select>
				</label>
				<label class="profile-modal__field profile-modal__field--full">
					<span>Righe dell'ordine</span>
					<div id="order-items" class="order-items"></div>
					<button type="button" class="profile-modal__cancel" data-order-add-item>+ Aggiungi riga</button>
				</label>
				<label class="profile-modal__field profile-modal__field--full">
					<span>Causale / note (riportata in fattura)</span>
					<textarea name="notes" class="form-control" rows="3" maxlength="2000"></textarea>
				</label>
				<p class="profile-modal__field profile-modal__field--full order-totals" aria-live="polite">
					Imponibile <strong id="order-subtotal">0,00</strong> IVA <strong id="order-tax">0,00</strong> Totale <strong id="order-total">0,00</strong>
				</p>
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

	<template id="order-item-template">
		<div class="order-item">
			<input type="text" name="item_description[]" class="form-control" placeholder="Descrizione" maxlength="500" required aria-label="Descrizione">
			<input type="number" name="item_quantity[]" class="form-control" value="1" step="1" min="1" required aria-label="Quantità">
			<input type="number" name="item_unit_price[]" class="form-control" placeholder= "Prezzo unit."  step="0.01" min="0.00" required aria-label="Prezzo unitario">
			<select name="item_vat_rate[]" class="form-control" aria-label="Aliquota IVA">
				<option value="22" selected>IVA 22%</option>
				<option value="10">IVA 10%</option>
				<option value="5">IVA 5%</option>
				<option value="4">IVA 4%</option>
				<option value="0">IVA 0%</option>
			</select>
			<output class="order-item__total" aria-label="Totale riga">0,00</output>
			<button type="button" " data-order-remove-item aria-label="Rimuovi riga">Reset</button>
		</div>
		
</template>

	<script>
	(function () {
		const box = () => document.getElementById('order-items');
		const tpl = () => document.getElementById('order-item-template');
		const round2 = (n) => Math.round((n + Number.EPSILON) * 100) / 100;
		const fmt = (n) => n.toLocaleString('it-IT', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

		function addRow() {
			if (!box() || !tpl()) return;
			box().appendChild(tpl().content.firstElementChild.cloneNode(true));
		}

		function recalc() {
			let subtotal = 0, tax = 0;
			document.querySelectorAll('#order-items .order-item').forEach((row) => {
				const q = parseFloat(row.querySelector('[name="item_quantity[]"]').value) || 0;
				const p = parseFloat(row.querySelector('[name="item_unit_price[]"]').value) || 0;
				const rate = parseFloat(row.querySelector('[name="item_vat_rate[]"]').value) || 0;
				const taxable = round2(q * p);
				const vat = round2(taxable * rate / 100);
				row.querySelector('.order-item__total').textContent = fmt(round2(taxable + vat));
				subtotal = round2(subtotal + taxable);
				tax = round2(tax + vat);
			});
			const set = (id, value) => { const el = document.getElementById(id); if (el) el.textContent = fmt(value); };
			set('order-subtotal', subtotal);
			set('order-tax', tax);
			set('order-total', round2(subtotal + tax));
		}

		function resetRows() {
			if (!box()) return;
			box().innerHTML = '';
			addRow();
			recalc();
		}

		document.addEventListener('click', (event) => {
			if (event.target.closest('[data-order-add-item]')) {
				addRow();
				recalc();
			}
			const removeBtn = event.target.closest('[data-order-remove-item]');
			if (removeBtn) {
				if (box().children.length > 1) {
					removeBtn.closest('.order-item').remove();
				} else {
					resetRows();
				}
				recalc();
			}
			if (event.target.closest('[data-order-modal-open]')) {
				resetRows();
			}
		});
		document.addEventListener('input', (event) => {
			if (event.target.closest('#order-items')) recalc();
		});
		document.addEventListener('change', (event) => {
			if (event.target.closest('#order-items')) recalc();
		});
		document.addEventListener('submit', (event) => {
			if (event.target && event.target.id === 'order-form') setTimeout(resetRows, 350);
		});
		resetRows();
	})();
	</script>























































































































































































































































































































































































































