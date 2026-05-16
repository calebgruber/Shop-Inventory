      </div><!-- /.page-wrapper -->
    </div><!-- /.page -->

    <!-- PAGE-LEVEL SCRIPTS -->
    <script src="../dist/libs/tom-select/dist/js/tom-select.base.min.js?1778865600"></script>
    <script src="../dist/js/tabler.min.js?1778865600"></script>

    <!-- SI ENHANCEMENTS: loader, live clock, fade-in, card accents -->
    <script>
      (function () {
        /* PAGE LOADER */
        var loader = document.getElementById('si-page-loader');
        function hideLoader() {
          if (!loader) return;
          loader.classList.add('si-loader-hidden');
          setTimeout(function () { loader.style.display = 'none'; }, 400);
        }
        if (document.readyState === 'complete') {
          setTimeout(hideLoader, 200);
        } else {
          window.addEventListener('load', function () { setTimeout(hideLoader, 200); });
        }

        /* LIVE DATETIME */
        var liveHM   = document.getElementById('si-live-hours-minutes');
        var liveSec  = document.getElementById('si-live-seconds');
        var liveDate = document.getElementById('si-live-date');
        if (liveHM && liveSec && liveDate) {
          function updateLiveClock() {
            var now = new Date();
            var parts = new Intl.DateTimeFormat('en-US', {
              timeZone: 'America/New_York',
              hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
            }).formatToParts(now);
            var part = function (type) {
              var item = parts.find(function (e) { return e.type === type; });
              return item ? item.value : '';
            };
            liveHM.textContent   = part('hour') + ':' + part('minute');
            liveSec.textContent  = ':' + part('second') + ' ' + part('dayPeriod');
            liveDate.textContent = now.toLocaleDateString('en-US', {
              timeZone: 'America/New_York', year: 'numeric', month: '2-digit', day: '2-digit'
            });
          }
          updateLiveClock();
          setInterval(updateLiveClock, 1000);
        }

        /* SCROLL-TRIGGERED FADE-IN */
        document.querySelectorAll('.row').forEach(function (row) {
          row.querySelectorAll('.card').forEach(function (card, i) {
            card.classList.add('si-animate');
            card.style.animationDelay = (i * 0.07) + 's';
          });
        });
        document.querySelectorAll('.page-header, .alert, table.table, .list-group').forEach(function (el, i) {
          el.classList.add('si-animate');
          el.style.animationDelay = (i * 0.05) + 's';
        });
        if ('IntersectionObserver' in window) {
          document.querySelectorAll('.si-animate').forEach(function (el) {
            el.style.opacity = '0';
            el.style.animationPlayState = 'paused';
          });
          var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
              if (entry.isIntersecting) {
                entry.target.style.animationPlayState = 'running';
                io.unobserve(entry.target);
              }
            });
          }, { threshold: 0.07 });
          document.querySelectorAll('.si-animate').forEach(function (el) { io.observe(el); });
        }

        /* CARD ACCENT RANDOMIZATION */
        var palette = [
          ['#2563eb','#fff'], ['#1d4ed8','#fff'], ['#16a34a','#fff'],
          ['#15803d','#fff'], ['#0369a1','#fff'], ['#0891b2','#fff'],
          ['#0d9488','#fff'], ['#7c3aed','#fff'], ['#6d28d9','#fff'],
          ['#9333ea','#fff'], ['#be185d','#fff'], ['#9f1239','#fff'],
          ['#dc2626','#fff'], ['#b45309','#fff'], ['#c2410c','#fff'],
          ['#d97706','#000'], ['#65a30d','#000'], ['#0e7490','#fff'],
          ['#1e40af','#fff'], ['#7e22ce','#fff'],
        ];
        document.querySelectorAll('.card').forEach(function (card) {
          var entry = palette[Math.floor(Math.random() * palette.length)];
          card.style.setProperty('--si-card-accent', entry[0]);
          card.style.setProperty('--si-card-text',   entry[1]);
        });
      })();
    </script>
  </body>
</html>
