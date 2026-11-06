/* Local site fallback driver: entrance animations + background motion FX.
   Safe alongside Elementor: class additions are idempotent; motion-fx layer
   creation is skipped when Elementor's runtime already controls it. */
(function () {
  // Guard remote admin-ajax / REST fetches when running from file:// (they can only fail)
  if (location.protocol === 'file:' && window.fetch) {
    var guardRe = /wp-admin\/admin-ajax\.php|\/wp-json\//;
    var origFetch = window.fetch.bind(window);
    window.fetch = function (input, init) {
      try {
        var u = (typeof input === 'string') ? input : (input && input.url) || '';
        if (guardRe.test(u)) return Promise.resolve(new Response('{}', { status: 200, headers: { 'Content-Type': 'application/json' } }));
      } catch (e) {}
      return origFetch(input, init);
    };
  }
  function parseDs(el) {
    try { return JSON.parse(el.getAttribute('data-settings') || '{}') || {}; } catch (e) { return {}; }
  }

  function startEntrance() {
    var els = Array.prototype.slice.call(document.querySelectorAll('.elementor-invisible'));
    if (!els.length) return;

    if (!document.getElementById('ap-animated-fix')) {
      var st = document.createElement('style');
      st.id = 'ap-animated-fix';
      st.textContent = [
        '.animated { animation-fill-mode: both !important; -webkit-animation-fill-mode: both !important; }',
        '@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } } .fadeIn { animation-name: fadeIn; }',
        '@keyframes fadeInUp { from { opacity: 0; transform: translate3d(0, 100%, 0); } to { opacity: 1; transform: none; } } .fadeInUp { animation-name: fadeInUp; }',
        '@keyframes slideInUp { from { transform: translate3d(0, 100%, 0); visibility: visible; } to { transform: translate3d(0, 0, 0); } } .slideInUp { animation-name: slideInUp; }',
        '@keyframes slideInLeft { from { transform: translate3d(-100%, 0, 0); visibility: visible; } to { transform: translate3d(0, 0, 0); } } .slideInLeft { animation-name: slideInLeft; }',
        '@keyframes slideInDown { from { transform: translate3d(0, -100%, 0); visibility: visible; } to { transform: translate3d(0, 0, 0); } } .slideInDown { animation-name: slideInDown; }',
        '@keyframes zoomIn { from { opacity: 0; transform: scale3d(0.3, 0.3, 0.3); } 50% { opacity: 1; } } .zoomIn { animation-name: zoomIn; }'
      ].join('\n');
      document.head.appendChild(st);
    }

    function triggerEl(el) {
      if (!el || !el.classList.contains('elementor-invisible')) return;
      var name = el.dataset.animName || 'fadeIn';
      var delay = parseInt(el.dataset.animDelay || '0', 10) || 0;

      if (delay > 0) {
        setTimeout(function () {
          el.classList.remove('elementor-invisible');
          el.classList.add('animated', name);
        }, delay);
      } else {
        el.classList.remove('elementor-invisible');
        el.classList.add('animated', name);
      }
    }

    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (ent) {
        if (!ent.isIntersecting) return;
        io.unobserve(ent.target);
        triggerEl(ent.target);
      });
    }, { rootMargin: '0px 0px -20px 0px', threshold: 0 });

    var inViewportEls = [];
    var vh = window.innerHeight || document.documentElement.clientHeight || 800;

    els.forEach(function (el) {
      var name = el.dataset.animName;
      var delay = el.dataset.animDelay;
      if (!name) {
        var o = parseDs(el);
        name = o._animation || o.animation;
        var d = (typeof o._animation_delay !== 'undefined') ? o._animation_delay : o.animation_delay;
        delay = (typeof d !== 'undefined') ? String(d) : '0';
        if (name && name !== 'none') {
          el.dataset.animName = name;
          el.dataset.animDelay = delay;
        } else {
          el.classList.remove('elementor-invisible');
          return;
        }
      }

      var rect = el.getBoundingClientRect();
      if (rect.top < vh && rect.bottom > 0) {
        inViewportEls.push(el);
      } else {
        io.observe(el);
      }
    });

    var carousels = Array.prototype.slice.call(document.querySelectorAll('.elementor-widget-n-carousel, .e-widget-swiper, .swiper'));
    if (carousels.length) {
      var carouselIo = new IntersectionObserver(function (entries) {
        entries.forEach(function (ent) {
          if (!ent.isIntersecting) return;
          carouselIo.unobserve(ent.target);
          var invis = ent.target.querySelectorAll('.elementor-invisible');
          for (var i = 0; i < invis.length; i++) {
            triggerEl(invis[i]);
          }
        });
      }, { rootMargin: '0px 0px 50px 0px', threshold: 0 });

      carousels.forEach(function (c) {
        carouselIo.observe(c);
      });

      document.addEventListener('click', function (e) {
        if (e.target && e.target.closest && e.target.closest('.swiper-button-next, .swiper-button-prev, .swiper-pagination-bullet, .elementor-swiper-button')) {
          var c = e.target.closest('.elementor-widget-n-carousel, .e-widget-swiper, .swiper');
          if (c) {
            setTimeout(function () {
              var invis = c.querySelectorAll('.elementor-invisible');
              for (var i = 0; i < invis.length; i++) triggerEl(invis[i]);
            }, 50);
          }
        }
      });
    }

    if (inViewportEls.length > 0) {
      requestAnimationFrame(function () {
        setTimeout(function () {
          inViewportEls.forEach(function (el) {
            triggerEl(el);
          });
        }, 60);
      });
    }

    window.addEventListener('resize', function () {
      var remaining = document.querySelectorAll('.elementor-invisible');
      var currVh = window.innerHeight || 800;
      for (var i = 0; i < remaining.length; i++) {
        var r = remaining[i].getBoundingClientRect();
        if (r.top < currVh && r.bottom > 0 && r.width > 0 && r.height > 0) {
          triggerEl(remaining[i]);
        }
      }
    });
  }

  function startMotionFx() {
    var hosts = Array.prototype.slice.call(document.querySelectorAll('[data-settings*="motion_fx"]'));
    hosts.forEach(function (host) {
      var o = parseDs(host);
      if (!o || o.background_motion_fx_motion_fx_scrolling !== 'yes') return;
      var hasX = o.background_motion_fx_translateX_effect === 'yes';
      var hasY = o.background_motion_fx_translateY_effect === 'yes';
      var hasScale = o.background_motion_fx_scale_effect === 'yes';
      if (!hasX && !hasY && !hasScale) return;

      var container = host.querySelector(':scope > .elementor-motion-effects-container');
      if (!container) {
        container = document.createElement('div');
        container.className = 'elementor-motion-effects-container';
        var newLayer = document.createElement('div');
        newLayer.className = 'elementor-motion-effects-layer';
        container.appendChild(newLayer);
        host.insertBefore(container, host.firstChild);
        host.classList.add('elementor-motion-effects-element');
        host.classList.add('elementor-motion-effects-element-type-background');
      }
      var layer = host.querySelector(':scope > .elementor-motion-effects-container > .elementor-motion-effects-layer');
      if (!layer) return;

      var sx = hasX ? (parseFloat((o.background_motion_fx_translateX_speed || {}).size) || 1) : 0;
      var sy = hasY ? (parseFloat((o.background_motion_fx_translateY_speed || {}).size) || 1) : 0;
      var dx = (o.background_motion_fx_translateX_direction === 'negative') ? -1 : 1;
      var dy = (o.background_motion_fx_translateY_direction === 'negative') ? -1 : 1;

      var rangeX = (o.background_motion_fx_translateX_affectedRange || {}).sizes || {};
      var startX = (typeof rangeX.start === 'number' ? rangeX.start : 0) / 100;
      var endX = (typeof rangeX.end === 'number' && rangeX.end > 0 ? rangeX.end : 80) / 100;

      if (hasX || hasY) {
        layer.style.width = '110%';
        layer.style.height = '100%';
      }

      var ticking = false;
      function update() {
        ticking = false;
        var r = host.getBoundingClientRect();
        var vh = window.innerHeight || 1;
        var totalDist = vh + r.height;
        var currentDist = vh - r.top;
        var rawP = Math.max(0, Math.min(1, currentDist / totalDist));

        if (hasX) {
          var clampedP = Math.max(0, Math.min(1, (rawP - startX) / (endX - startX)));
          var hostW = host.offsetWidth || window.innerWidth;
          var maxShiftX = hostW * 0.1 * sx;
          var tx = dx * clampedP * maxShiftX;
          layer.style.setProperty('--translateX', tx.toFixed(2) + 'px');
          layer.style.transform = 'translateX(var(--translateX))';
        }
        if (hasY) {
          var maxShiftY = r.height * 0.1 * sy;
          var ty = dy * rawP * maxShiftY;
          layer.style.setProperty('--translateY', ty.toFixed(2) + 'px');
          layer.style.transform = 'translate(var(--translateX, 0px), var(--translateY))';
        }
        if (hasScale) {
          var scaleSpeed = parseFloat((o.background_motion_fx_scale_speed || {}).size) || 2;
          var scale = 1 + (rawP * 0.2 * (scaleSpeed / 2));
          layer.style.setProperty('--scale', scale.toFixed(3));
          layer.style.transform = 'scale(var(--scale))';
        }
      }

      function onScroll() {
        update();
      }

      window.addEventListener('scroll', onScroll, { passive: true });
      window.addEventListener('resize', onScroll);
      update();
    });
  }

  function startLazyBg() {
    // Elementor's lazy-load background CSS removes bg images from sections n+4 onwards
    // (".e-con.e-parent:nth-of-type(n+4):not(.e-lazyloaded)"); the runtime normally adds
    // the class when sections approach the viewport. Offline clone: backgrounds are local
    // files, so we can re-enable them eagerly at startup with identical end state.
    var els = document.querySelectorAll('.e-con.e-parent:nth-of-type(n+4)');
    for (var i = 0; i < els.length; i++) {
      if (!els[i].classList.contains('e-lazyloaded')) els[i].classList.add('e-lazyloaded', 'e-no-lazyload');
    }
  }

  function boot() {
    window.__apFallbackActive = true;
    try { startEntrance(); } catch (e) { console.error('entrance error', e); }
    try { startMotionFx(); } catch (e) { console.error('motionFx error', e); }
    try { startLazyBg(); } catch (e) { console.error('lazyBg error', e); }
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
})();
