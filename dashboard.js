const sidebar = document.getElementById('sidebar');
document.getElementById('menu')?.addEventListener('click', () => sidebar.classList.toggle('open'));

document.querySelectorAll('[data-open-modal]').forEach(trigger => {
  trigger.addEventListener('click', () => {
    const id = trigger.getAttribute('data-open-modal');
    document.getElementById(id)?.classList.add('open');
  });
});

document.querySelectorAll('[data-close-modal]').forEach(trigger => {
  trigger.addEventListener('click', () => {
    trigger.closest('.modal-overlay')?.classList.remove('open');
  });
});

document.querySelectorAll('.modal-overlay').forEach(overlay => {
  overlay.addEventListener('click', e => {
    if (e.target === overlay) overlay.classList.remove('open');
  });
});

document.addEventListener('keydown', e => {
  if (e.key === 'Escape') {
    document.querySelectorAll('.modal-overlay.open').forEach(o => o.classList.remove('open'));
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
  bell.addEventListener('click', (e) => {
    e.stopPropagation();
    const dropdown = bell.parentElement.querySelector('.notif-dropdown');
    dropdown?.classList.toggle('open');
    document.getElementById('notifDot')?.style.setProperty('display', 'none');
  });
});

document.addEventListener('click', (e) => {
  document.querySelectorAll('.notif-dropdown.open').forEach(dropdown => {
    if (!dropdown.contains(e.target) && !dropdown.previousElementSibling?.contains(e.target)) {
      dropdown.classList.remove('open');
    }
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
