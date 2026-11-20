try {
  const savedTheme = localStorage.getItem('safaritrak-theme');
  if (savedTheme === 'light' || savedTheme === 'dark') {
    document.documentElement.dataset.theme = savedTheme;
  }
} catch (error) {
  console.warn('SafariTrak could not read the saved appearance preference.', error);
}

const sidebar = document.getElementById('sidebar');
let menuButton = document.getElementById('menu');
const sidebarCloseButton = document.getElementById('sidebarClose');
const usesStandardNavbar = sidebar?.hasAttribute('data-standard-navbar') ?? false;
const sidebarInertTargets = [
  document.querySelector('.app > main'),
  document.getElementById('appMapPanelsToggle'),
  document.getElementById('appMapBackground'),
  document.getElementById('appMapControls'),
].filter(Boolean);
let sidebarBackdrop = document.getElementById('sidebarBackdrop');
const mobileNavigationMedia = window.matchMedia('(max-width: 800px)');
let sidebarOpener = null;

if (usesStandardNavbar && sidebar && !menuButton) {
  menuButton = document.createElement('button');
  menuButton.type = 'button';
  menuButton.id = 'menu';
  menuButton.className = 'app-navbar-menu-toggle';
  menuButton.setAttribute('aria-label', 'Open navigation');
  menuButton.setAttribute('aria-controls', 'sidebar');
  menuButton.setAttribute('aria-expanded', 'false');
  menuButton.innerHTML = '<i class="fa-solid fa-bars" aria-hidden="true"></i>';
  document.body.append(menuButton);
}

function addAccessibleTooltips(root = document) {
  const selector = 'button[aria-label], a[aria-label], input[aria-label], select[aria-label], textarea[aria-label]';
  const controls = [];
  if (root instanceof Element && root.matches(selector)) controls.push(root);
  if (root.querySelectorAll) controls.push(...root.querySelectorAll(selector));

  controls.forEach(control => {
    if (!control.hasAttribute('title') || control.hasAttribute('data-safaritrak-generated-title')) {
      control.title = control.getAttribute('aria-label')?.trim() || '';
      control.setAttribute('data-safaritrak-generated-title', '');
    }
  });
}

addAccessibleTooltips();
new MutationObserver(records => {
  records.forEach(record => {
    if (record.type === 'attributes') {
      addAccessibleTooltips(record.target);
      return;
    }
    record.addedNodes.forEach(node => {
      if (node instanceof Element) addAccessibleTooltips(node);
    });
  });
}).observe(document.body, {
  attributes: true,
  attributeFilter: ['aria-label'],
  childList: true,
  subtree: true,
});

if (menuButton) {
  menuButton.setAttribute('aria-controls', 'sidebar');
  menuButton.setAttribute('aria-expanded', sidebar?.classList.contains('open') ? 'true' : 'false');
  menuButton.setAttribute('aria-label', 'Open navigation');
}

window.SafariTrakUI = window.SafariTrakUI || {};
window.SafariTrakUI.setStatus = (container, {
  state = 'empty',
  title,
  message,
  retryLabel,
  onRetry,
  linkLabel,
  linkHref,
} = {}) => {
  if (!container) return;
  const stateNames = ['loading', 'empty', 'error', 'offline'];
  const resolvedState = stateNames.includes(state) ? state : 'error';
  const status = document.createElement('div');
  status.className = `ui-state ui-state--${resolvedState}`;
  status.setAttribute('role', resolvedState === 'error' ? 'alert' : 'status');
  status.setAttribute('aria-live', resolvedState === 'error' ? 'assertive' : 'polite');
  if (resolvedState === 'loading') {
    status.setAttribute('aria-busy', 'true');
    container.setAttribute('aria-busy', 'true');
  } else {
    container.removeAttribute('aria-busy');
  }

  const icon = document.createElement('span');
  icon.className = 'ui-state__icon';
  icon.setAttribute('aria-hidden', 'true');
  icon.textContent = resolvedState === 'loading' ? '' : ({
    empty: '○',
    error: '!',
    offline: '⌁',
  }[resolvedState]);
  status.append(icon);

  const content = document.createElement('span');
  content.className = 'ui-state__content';
  if (title) {
    const heading = document.createElement('strong');
    heading.className = 'ui-state__title';
    heading.textContent = title;
    content.append(heading);
  }
  if (message) {
    const description = document.createElement('span');
    description.className = 'ui-state__message';
    description.textContent = message;
    content.append(description);
  }
  status.append(content);

  if (retryLabel && typeof onRetry === 'function') {
    const retry = document.createElement('button');
    retry.className = 'ui-state__retry';
    retry.type = 'button';
    retry.textContent = retryLabel;
    retry.addEventListener('click', onRetry);
    status.append(retry);
  }
  if (linkLabel && linkHref) {
    const link = document.createElement('a');
    link.className = 'ui-state__link';
    link.href = linkHref;
    link.textContent = linkLabel;
    status.append(link);
  }
  let wrapper = status;
  if (container instanceof HTMLUListElement) {
    wrapper = document.createElement('li');
    wrapper.className = 'ui-state-item';
    wrapper.append(status);
  }
  container.replaceChildren(wrapper);
};

if (!usesStandardNavbar && !sidebarBackdrop && sidebar) {
  sidebarBackdrop = document.createElement('button');
  sidebarBackdrop.type = 'button';
  sidebarBackdrop.id = 'sidebarBackdrop';
  sidebarBackdrop.className = 'sidebar-backdrop';
  sidebarBackdrop.setAttribute('aria-label', 'Close navigation');
  sidebar.before(sidebarBackdrop);
}

if (
  !usesStandardNavbar
  && !document.querySelector('.mobile-bottom-nav')
  && sidebar?.querySelector('nav a[href="messages.php"]')
  && sidebar.querySelector('nav a[href="my-journeys.php"]')
) {
  const currentPage = window.location.pathname.split('/').pop();
  const items = [
    { href: 'index.php', icon: 'fa-solid fa-map', label: 'Map', pages: ['index.php'] },
    { href: 'messages.php', icon: 'fa-regular fa-message', label: 'Chat', pages: ['messages.php'] },
    { href: 'places.php', icon: 'fa-solid fa-map-pin', label: 'Places', pages: ['places.php'] },
    { href: 'my-journeys.php', icon: 'fa-solid fa-route', label: 'Trips', pages: ['my-journeys.php', 'start-journey.php', 'view-journey.php', 'live-tracking.php'] },
  ];
  const nav = document.createElement('nav');
  nav.className = 'mobile-bottom-nav';
  nav.setAttribute('aria-label', 'Quick navigation');

  for (const item of items) {
    const link = document.createElement('a');
    link.href = item.href;
    if (item.pages.includes(currentPage)) {
      link.classList.add('active');
      link.setAttribute('aria-current', 'page');
    }
    const icon = document.createElement('i');
    icon.className = item.icon;
    icon.setAttribute('aria-hidden', 'true');
    const label = document.createElement('span');
    label.textContent = item.label;
    link.append(icon, label);
    if (item.label === 'Chat') {
      const unreadCount = sidebar.querySelector('nav a[href="messages.php"] em')?.textContent.trim();
      if (unreadCount) {
        const badge = document.createElement('em');
        badge.textContent = unreadCount;
        link.append(badge);
      }
    }
    nav.append(link);
  }

  const more = document.createElement('button');
  more.type = 'button';
  more.dataset.mobileMore = '';
  more.setAttribute('aria-controls', 'sidebar');
  more.setAttribute('aria-expanded', 'false');
  more.setAttribute('aria-label', 'Open more navigation');
  const moreIcon = document.createElement('i');
  moreIcon.className = 'fa-solid fa-ellipsis';
  moreIcon.setAttribute('aria-hidden', 'true');
  const moreLabel = document.createElement('span');
  moreLabel.textContent = 'More';
  more.append(moreIcon, moreLabel);
  nav.append(more);
  document.body.append(nav);
}

if (!window.__safaritrakCsrfFetch) {
  const nativeFetch = window.fetch.bind(window);
  let csrfTokenRequest;

  window.fetch = async (input, init = {}) => {
    const requestUrl = new URL(typeof input === 'string' ? input : input.url, window.location.href);
    const requestMethod = (init.method || (typeof input === 'string' ? 'GET' : input.method) || 'GET').toUpperCase();
    const mutating = ['POST', 'PUT', 'PATCH', 'DELETE'].includes(requestMethod);

    if (mutating && requestUrl.origin === window.location.origin) {
      if (!csrfTokenRequest) {
        const tokenUrl = new URL('backend/api/csrf-token.php', window.location.href);
        csrfTokenRequest = nativeFetch(tokenUrl, { credentials: 'same-origin' })
          .then(async response => {
            const payload = await response.json();
            if (!response.ok || !payload.csrf_token) {
              throw new Error('Could not obtain a request security token.');
            }
            return payload.csrf_token;
          })
          .catch(error => {
            csrfTokenRequest = null;
            throw error;
          });
      }

      const headers = new Headers(init.headers || (typeof input === 'string' ? undefined : input.headers));
      headers.set('X-CSRF-Token', await csrfTokenRequest);
      init = { ...init, headers, credentials: init.credentials || 'same-origin' };
    }

    return nativeFetch(input, init);
  };

  window.__safaritrakCsrfFetch = true;
}

function syncSidebarAccessibility() {
  if (!sidebar || usesStandardNavbar) return;
  const isMobile = mobileNavigationMedia.matches;
  if (!isMobile && sidebar.classList.contains('open')) {
    sidebar.classList.remove('open');
    sidebarBackdrop?.classList.remove('open');
    menuButton?.setAttribute('aria-expanded', 'false');
    menuButton?.setAttribute('aria-label', 'Open navigation');
    document.querySelector('[data-mobile-more]')?.setAttribute('aria-expanded', 'false');
  }
  const isOpen = isMobile && sidebar.classList.contains('open');
  sidebar.toggleAttribute('inert', isMobile && !isOpen);
  if (isMobile && !isOpen) {
    sidebar.setAttribute('aria-hidden', 'true');
  } else {
    sidebar.removeAttribute('aria-hidden');
  }
  if (isMobile && isOpen) {
    sidebar.setAttribute('role', 'dialog');
    sidebar.setAttribute('aria-modal', 'true');
  } else {
    sidebar.removeAttribute('role');
    sidebar.removeAttribute('aria-modal');
  }
  sidebarInertTargets.forEach(target => target.toggleAttribute('inert', isOpen));
}

function openSidebar(opener = menuButton) {
  if (!sidebar) return;
  sidebarOpener = opener;
  sidebar.classList.add('open');
  if (mobileNavigationMedia.matches) {
    sidebar.style.width = 'min(292px, calc(100vw - 52px))';
    sidebar.style.maxWidth = 'calc(100vw - 52px)';
    sidebar.style.height = '100dvh';
    sidebar.style.inset = '0 auto 0 0';
    sidebar.style.flexDirection = 'column';
    sidebar.style.alignItems = 'stretch';
    sidebar.style.borderRadius = '0';
    sidebar.style.transform = 'translateX(0)';
    sidebar.style.visibility = 'visible';
    const nav = sidebar.querySelector('nav');
    if (nav) {
      nav.style.display = 'grid';
      nav.style.width = '100%';
      nav.style.gridTemplateColumns = 'minmax(0, 1fr)';
      nav.style.gap = '5px';
    }
    sidebar.querySelectorAll('nav a').forEach(link => {
      link.style.width = '260px';
      link.style.maxWidth = 'none';
      link.style.height = '46px';
      link.style.display = 'flex';
      link.style.alignItems = 'center';
      link.style.justifyContent = 'flex-start';
      link.style.gap = '12px';
      link.style.padding = '0 13px';
      link.style.overflow = 'visible';
      const label = link.querySelector('.nav-link-label');
      if (label) {
        label.style.display = 'block';
        label.style.maxWidth = 'none';
        label.style.opacity = '1';
        label.style.transform = 'none';
      }
    });
  }
  sidebarBackdrop?.classList.add('open');
  menuButton?.setAttribute('aria-expanded', 'true');
  menuButton?.setAttribute('aria-label', 'Close navigation');
  document.querySelector('[data-mobile-more]')?.setAttribute('aria-expanded', 'true');
  syncSidebarAccessibility();
  sidebarCloseButton?.focus();
}

function closeSidebar({ restoreFocus = false } = {}) {
  sidebar?.classList.remove('open');
  if (sidebar && mobileNavigationMedia.matches) {
    sidebar.style.transform = 'translateX(-105%)';
    sidebar.style.visibility = 'hidden';
  }
  sidebarBackdrop?.classList.remove('open');
  menuButton?.setAttribute('aria-expanded', 'false');
  menuButton?.setAttribute('aria-label', 'Open navigation');
  document.querySelector('[data-mobile-more]')?.setAttribute('aria-expanded', 'false');
  syncSidebarAccessibility();
  if (restoreFocus && mobileNavigationMedia.matches) (sidebarOpener || menuButton)?.focus();
  sidebarOpener = null;
}

menuButton?.addEventListener('click', () => {
  if (sidebar?.classList.contains('open')) {
    closeSidebar();
  } else {
    openSidebar(menuButton);
  }
});
sidebarCloseButton?.addEventListener('click', () => closeSidebar({ restoreFocus: true }));
sidebarBackdrop?.addEventListener('click', () => closeSidebar({ restoreFocus: true }));
sidebar?.querySelectorAll('a[href]').forEach(link => link.addEventListener('click', () => closeSidebar()));
document.querySelector('[data-mobile-more]')?.addEventListener('click', event => openSidebar(event.currentTarget));
syncSidebarAccessibility();

window.addEventListener('resize', () => {
  const wasOpen = sidebar?.classList.contains('open');
  syncSidebarAccessibility();
  if (wasOpen && !mobileNavigationMedia.matches) {
    sidebar?.querySelector('nav a[href]')?.focus();
    sidebarOpener = null;
  }
});

const pagePanelsToggle = document.getElementById('appMapPanelsToggle');
function clearPagePanelFocus() {
  document.body.classList.remove('page-panel-focus');
  document.body.classList.remove('map-focus-mode');
  document.querySelectorAll('.is-page-panel-selected').forEach(panel => {
    panel.classList.remove('is-page-panel-selected');
  });
  if (pagePanelsToggle) pagePanelsToggle.hidden = true;
  document.dispatchEvent(new Event('safaritrak-panels-restored'));
}

document.querySelectorAll('.app--map-layout:not(.app--explore):not(.app--journeys) .content').forEach(content => {
  const focusMapReviewPanel = event => {
    if (!document.getElementById('appMapBackground')) return;
    const mapReviewTarget = event.target.closest('[data-map-review]');
    if (!mapReviewTarget) return;

    let panel = mapReviewTarget;
    while (panel?.parentElement && panel.parentElement !== content) {
      if (panel.parentElement.matches('.grid, .lower, .stat-grid')) break;
      panel = panel.parentElement;
    }
    const panelParent = panel?.parentElement;
    if (!panelParent || (panelParent !== content && !panelParent.matches('.grid, .lower, .stat-grid'))) return;
    document.querySelectorAll('.is-page-panel-selected').forEach(selected => {
      selected.classList.remove('is-page-panel-selected');
    });
    panel.classList.add('is-page-panel-selected');
    document.body.classList.add('page-panel-focus');
    if (pagePanelsToggle) pagePanelsToggle.hidden = false;
  };
  content.addEventListener('focusin', focusMapReviewPanel);
  content.addEventListener('click', focusMapReviewPanel);
});

pagePanelsToggle?.addEventListener('click', clearPagePanelFocus);
document.addEventListener('keydown', event => {
  if (event.key === 'Escape' && (
    document.body.classList.contains('page-panel-focus')
    || document.body.classList.contains('map-focus-mode')
  )) {
    clearPagePanelFocus();
  }
});

document.querySelectorAll('[data-theme-toggle]').forEach(button => {
  button.addEventListener('click', () => {
    const nextTheme = document.documentElement.dataset.theme === 'light' ? 'dark' : 'light';
    document.documentElement.dataset.theme = nextTheme;
    button.setAttribute('aria-label', `Switch to ${nextTheme === 'dark' ? 'light' : 'dark'} theme`);
    button.setAttribute('title', `Switch to ${nextTheme === 'dark' ? 'light' : 'dark'} theme`);
    try {
      localStorage.setItem('safaritrak-theme', nextTheme);
    } catch (error) {
      // The theme remains active for this page even when browser storage is unavailable.
    }
  });
});

document.querySelectorAll('[data-open-modal]').forEach(trigger => {
  trigger.addEventListener('click', () => {
    const id = trigger.getAttribute('data-open-modal');
    const overlay = document.getElementById(id);
    if (!overlay) return;
    if (overlay.parentElement !== document.body) document.body.append(overlay);
    overlay.classList.add('open');
    document.body.classList.add('modal-overlay-open');
  });
});

document.querySelectorAll('[data-close-modal]').forEach(trigger => {
  trigger.addEventListener('click', () => {
    const overlay = trigger.closest('.modal-overlay');
    overlay?.classList.remove('open');
    if (!document.querySelector('.modal-overlay.open')) document.body.classList.remove('modal-overlay-open');
  });
});

document.querySelectorAll('.modal-overlay').forEach(overlay => {
  overlay.addEventListener('click', e => {
    if (e.target === overlay) {
      overlay.classList.remove('open');
      if (!document.querySelector('.modal-overlay.open')) document.body.classList.remove('modal-overlay-open');
    }
  });
});

document.addEventListener('keydown', e => {
  if (e.key === 'Escape') {
    document.querySelectorAll('.modal-overlay.open').forEach(o => o.classList.remove('open'));
    if (!document.querySelector('.modal-overlay.open')) document.body.classList.remove('modal-overlay-open');
    if (sidebar?.classList.contains('open')) {
      closeSidebar({ restoreFocus: true });
    }
  }
  if (e.key !== 'Tab' || !sidebar?.classList.contains('open') || !mobileNavigationMedia.matches) return;

  const focusable = [...sidebar.querySelectorAll(
    'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
  )].filter(element => !element.hasAttribute('hidden') && element.getAttribute('aria-hidden') !== 'true');
  if (focusable.length === 0) {
    e.preventDefault();
    sidebarCloseButton?.focus();
    return;
  }

  const first = focusable[0];
  const last = focusable[focusable.length - 1];
  if (e.shiftKey && (document.activeElement === first || !sidebar.contains(document.activeElement))) {
    e.preventDefault();
    last.focus();
  } else if (!e.shiftKey && (document.activeElement === last || !sidebar.contains(document.activeElement))) {
    e.preventDefault();
    first.focus();
  }
});

document.querySelectorAll('[data-tab-group]').forEach(group => {
  const name = group.getAttribute('data-tab-group');
  group.querySelectorAll('.tab').forEach(tab => {
    tab.addEventListener('click', () => {
      group.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
      tab.classList.add('active');
      const value = tab.getAttribute('data-tab');
      document.querySelectorAll('[data-tab-panel-group="' + name + '"]').forEach(panel => {
        panel.classList.toggle('active', panel.getAttribute('data-tab-panel') === value);
      });
    });
  });
});

document.querySelectorAll('.notif-bell').forEach(bell => {
  const dropdownId = bell.parentElement.querySelector('.notif-dropdown')?.id;
  if (dropdownId) bell.setAttribute('aria-controls', dropdownId);
  bell.setAttribute('aria-expanded', 'false');
  bell.addEventListener('click', (e) => {
    e.stopPropagation();
    const dropdown = bell.parentElement.querySelector('.notif-dropdown');
    const isOpen = dropdown?.classList.toggle('open') || false;
    bell.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    document.getElementById('notifDot')?.style.setProperty('display', 'none');
  });
});

document.addEventListener('click', (e) => {
  document.querySelectorAll('.notif-dropdown.open').forEach(dropdown => {
    if (!dropdown.contains(e.target) && !dropdown.previousElementSibling?.contains(e.target)) {
      dropdown.classList.remove('open');
      dropdown.parentElement.querySelector('.notif-bell')?.setAttribute('aria-expanded', 'false');
    }
  });
});
document.addEventListener('keydown', event => {
  if (event.key !== 'Escape') return;
  document.querySelectorAll('.notif-dropdown.open').forEach(dropdown => {
    dropdown.classList.remove('open');
    dropdown.parentElement.querySelector('.notif-bell')?.focus();
    dropdown.parentElement.querySelector('.notif-bell')?.setAttribute('aria-expanded', 'false');
  });
});
document.addEventListener('DOMContentLoaded', () => {
  const profileAvatarBtn = document.getElementById('profileAvatarBtn');
  const profileDropdownMenu = document.getElementById('profileDropdownMenu');
  const profileDropdownWrap = document.getElementById('profileDropdownWrap');

  if (profileAvatarBtn && profileDropdownMenu) {
    profileAvatarBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      const isExpanded = profileDropdownMenu.classList.toggle('show');
      profileAvatarBtn.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
    });

    document.addEventListener('click', (e) => {
      if (profileDropdownWrap && !profileDropdownWrap.contains(e.target)) {
        if (profileDropdownMenu.classList.contains('show')) {
          profileDropdownMenu.classList.remove('show');
          profileAvatarBtn.setAttribute('aria-expanded', 'false');
        }
      }
    });

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && profileDropdownMenu.classList.contains('show')) {
        profileDropdownMenu.classList.remove('show');
        profileAvatarBtn.setAttribute('aria-expanded', 'false');
      }
    });
  }
});
