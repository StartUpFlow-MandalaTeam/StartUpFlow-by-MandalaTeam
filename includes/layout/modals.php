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
