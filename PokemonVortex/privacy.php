<?php
require_once __DIR__ . '/includes/ui.php';
pv_page_start('Privacy', '', pv_is_logged_in());
?>
<main class="pv-content-wrap pv-narrow">
  <section class="pv-panel pv-prose">
    <span class="pv-eyebrow">PRIVACY</span>
    <h1>Privacy Policy</h1>
    <p>Pokemon Vortex NXT stores the information required to provide trainer accounts and persistent gameplay.</p>
    <h2>Information stored</h2>
    <p>Account records may include your username, email address, password hash, sign-in metadata and gameplay information such as Pokémon, items, battles, messages, trades, clan membership and progress.</p>
    <h2>Authentication and cookies</h2>
    <p>A session cookie is used to keep you signed in while you navigate the game. Passwords created through the current account system are stored as one-way password hashes rather than readable passwords.</p>
    <h2>Gameplay and community data</h2>
    <p>Information you intentionally share through trainer profiles, messages, trades or clans may be visible to other players as part of those features.</p>
    <h2>Data retention</h2>
    <p>Gameplay data is retained while the related trainer account exists, subject to the policies of the server operating this game.</p>
  </section>
</main>
<?php pv_page_end(); ?>
