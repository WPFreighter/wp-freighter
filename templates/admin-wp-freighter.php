<?php
/**
 * Tools → WP Freighter. Shell markup; rows, notices and dialogs are filled by assets/js/admin-app.js
 * from the localized wpFreighterSettings object. No framework.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}
?>
<svg width="0" height="0" style="position:absolute" aria-hidden="true">
	<defs>
		<symbol id="wpf-mark" viewBox="0 60 620 510">
			<path d="M115 71 H137 V267 H246 V437 H83 V267 H115 Z" fill="var(--wpf-cabin)" stroke="var(--wpf-cabin-line)" stroke-width="3" stroke-linejoin="round"/>
			<rect x="116" y="302" width="91" height="25" fill="#FF4F00"/>
			<path d="M138 143 H232 L216 177 L232 210 H138 Z" fill="#D90C1A"/>
			<rect x="299" y="225" width="76" height="73" fill="#FFB900"/><rect x="376" y="225" width="76" height="73" fill="#D90C1A"/><rect x="454" y="225" width="76" height="73" fill="#B1D8A9"/>
			<rect x="299" y="299" width="76" height="72" fill="#FE4E00"/><rect x="376" y="299" width="76" height="72" fill="#B1D8A9"/><rect x="454" y="299" width="76" height="72" fill="#6AB221"/><rect x="530" y="299" width="76" height="72" fill="#D90C1A"/>
			<rect x="299" y="371" width="76" height="65" fill="#D90C1A"/><rect x="376" y="371" width="76" height="65" fill="#FFB900"/><rect x="454" y="371" width="76" height="65" fill="#D90C1A"/><rect x="530" y="371" width="76" height="65" fill="#FE4E00"/>
			<path d="M16 435 H604 a123 123 0 0 1 -123 123 H139 A123 123 0 0 1 16 435 Z" fill="var(--wpf-hull)"/>
		</symbol>
	</defs>
</svg>

<div id="wpf" class="wpf" hidden>
	<div class="wpf-progress" id="wpf-progress" aria-hidden="true"></div>

	<header class="wpf-head">
		<div class="wpf-brand">
			<svg class="wpf-mark" aria-hidden="true"><use href="#wpf-mark"/></svg>
			<div>
				<h1>WP Freighter</h1>
				<p class="wpf-status" id="wpf-status"></p>
			</div>
		</div>
		<div class="wpf-head-actions">
			<button type="button" class="wpf-btn" data-action="login-main" id="wpf-login-main" hidden>Log in to main site</button>
			<button type="button" class="wpf-btn" data-action="clone-main">Clone main site</button>
			<button type="button" class="wpf-btn wpf-btn-primary" data-action="new">New tenant site</button>
			<div class="wpf-theme-wrap">
				<button type="button" class="wpf-btn wpf-btn-icon" id="wpf-theme" aria-label="Toggle light and dark. Right-click for system." aria-haspopup="menu" aria-expanded="false">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 3a9 9 0 0 0 0 18z" fill="currentColor" stroke="none"/></svg>
				</button>
				<div class="wpf-menu" id="wpf-theme-menu" role="menu" hidden>
					<button type="button" role="menuitemradio" data-theme-pick="light">Light</button>
					<button type="button" role="menuitemradio" data-theme-pick="dark">Dark</button>
					<button type="button" role="menuitemradio" data-theme-pick="system">System</button>
				</div>
			</div>
		</div>
	</header>

	<div id="wpf-notices"></div>

	<section class="wpf-section">
		<div class="wpf-section-head">
			<h2>Tenant sites <span class="wpf-count" id="wpf-count"></span></h2>
			<p class="wpf-hint" id="wpf-sites-hint"></p>
		</div>
		<div class="wpf-table-wrap">
			<table class="wpf-table" id="wpf-table">
				<thead><tr id="wpf-thead"></tr></thead>
				<tbody id="wpf-rows"></tbody>
			</table>
			<p class="wpf-empty" id="wpf-empty" hidden>No tenant sites yet. Clone the main site or create an empty one.</p>
		</div>
	</section>

	<section class="wpf-section wpf-settings">
		<div class="wpf-setting">
			<h2>Files</h2>
			<p class="wpf-hint">How much of <code>wp-content</code> tenants share. Changing mode does not move existing files.</p>
			<div class="wpf-choices" role="radiogroup" aria-label="Files mode">
				<label class="wpf-choice"><input type="radio" name="files" value="shared"><span><b>Shared</b>One <code>wp-content/</code> for every site: plugins, themes and uploads.</span></label>
				<label class="wpf-choice"><input type="radio" name="files" value="hybrid"><span><b>Hybrid</b>Shared plugins and themes; each site's uploads in <code>content/&lt;id&gt;/uploads/</code>.</span></label>
				<label class="wpf-choice"><input type="radio" name="files" value="dedicated"><span><b>Dedicated</b>A whole <code>content/&lt;id&gt;/</code> per site: its own plugins, themes and uploads.</span></label>
			</div>
		</div>
		<div class="wpf-setting">
			<h2>Domain mapping</h2>
			<p class="wpf-hint">How tenants are reached. Mapped domains still need DNS and the host pointed at this install.</p>
			<div class="wpf-choices" role="radiogroup" aria-label="Domain mapping">
				<label class="wpf-choice"><input type="radio" name="domain_mapping" value="off"><span><b>Off</b>Tenants ride on this site's domain. Reach them through the switcher or a magic login.</span></label>
				<label class="wpf-choice"><input type="radio" name="domain_mapping" value="on"><span><b>On</b>Each tenant answers on its own hostname. Set it in the table above.</span></label>
			</div>
		</div>
	</section>

	<div class="wpf-savebar" id="wpf-savebar" hidden>
		<span id="wpf-savebar-text">Unsaved changes</span>
		<button type="button" class="wpf-btn" data-action="discard">Discard</button>
		<button type="button" class="wpf-btn wpf-btn-primary" data-action="save">Save changes</button>
	</div>

	<dialog class="wpf-dialog" id="wpf-dialog-new">
		<form method="dialog" id="wpf-form-new">
			<h2>New tenant site</h2>
			<p class="wpf-hint">Only database tables are created, so this takes a few seconds.</p>
			<div class="wpf-fields">
				<label data-mode="off"><span>Label</span><input type="text" name="name" placeholder="Staging"></label>
				<label data-mode="on"><span>Domain</span><input type="text" name="domain" placeholder="example.com"></label>
				<label><span>Site title</span><input type="text" name="title" required></label>
				<label><span>Admin email</span><input type="email" name="email" required></label>
				<label><span>Admin username</span><input type="text" name="username" required autocomplete="off"></label>
				<label><span>Admin password</span><span class="wpf-input-row"><input type="text" name="password" required autocomplete="off" spellcheck="false"><button type="button" class="wpf-btn wpf-btn-small" data-action="regen-password">Regenerate</button></span></label>
			</div>
			<div class="wpf-dialog-actions">
				<button type="button" class="wpf-btn" data-action="close">Cancel</button>
				<button type="submit" class="wpf-btn wpf-btn-primary">Create site</button>
			</div>
		</form>
	</dialog>

	<dialog class="wpf-dialog" id="wpf-dialog-clone">
		<form method="dialog" id="wpf-form-clone">
			<h2>Clone <span id="wpf-clone-source"></span></h2>
			<p class="wpf-hint">Tables are copied to a new prefix; in hybrid and dedicated mode the content folder is copied too.</p>
			<div class="wpf-fields">
				<label data-mode="off"><span>Label for the copy</span><input type="text" name="name"></label>
				<label data-mode="on"><span>Domain for the copy</span><input type="text" name="domain" placeholder="example.com"></label>
			</div>
			<div class="wpf-dialog-actions">
				<button type="button" class="wpf-btn" data-action="close">Cancel</button>
				<button type="submit" class="wpf-btn wpf-btn-primary">Clone</button>
			</div>
		</form>
	</dialog>

	<dialog class="wpf-dialog" id="wpf-dialog-delete">
		<form method="dialog" id="wpf-form-delete">
			<h2>Delete <span id="wpf-delete-name"></span>?</h2>
			<p class="wpf-hint">Its database tables are dropped. There is no undo.</p>
			<p class="wpf-delete-files" id="wpf-delete-files" hidden></p>
			<div class="wpf-dialog-actions">
				<button type="button" class="wpf-btn" data-action="close">Cancel</button>
				<button type="submit" class="wpf-btn wpf-btn-danger">Delete permanently</button>
			</div>
		</form>
	</dialog>

	<div class="wpf-toast" id="wpf-toast" role="status" aria-live="polite" hidden></div>
</div>
<noscript><div class="notice notice-error"><p>WP Freighter's settings page needs JavaScript.</p></div></noscript>
