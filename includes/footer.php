    </main>
  </div>
</div>

<!-- Bottom tab bar (mobile) -->
<nav class="bottom-nav" aria-label="Quick navigation">
  <?php foreach ($psBottom as $bk): if (!isset($nav[$bk])) continue; $bi = $nav[$bk]; ?>
    <a href="<?= $bi[1] ?>" class="<?= $active === $bk ? 'active' : '' ?>">
      <svg fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="<?= $bi[2] ?>"/></svg>
      <?= $bi[0] === 'Compose Post' ? 'Compose' : $bi[0] ?>
    </a>
  <?php endforeach; ?>
  <button type="button" data-drawer-open aria-label="More">
    <svg fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 12a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM12.75 12a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM18.75 12a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z"/></svg>
    More
  </button>
</nav>
<script src="assets/js/charts.js?v=2"></script>
<script src="assets/js/ui.js?v=2"></script>
<script src="assets/js/app.js"></script>
</body>
</html>
