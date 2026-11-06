(() => {
  let theme = 'dark';
  try {
    theme = localStorage.getItem('safaritrak-theme') || theme;
  } catch (error) {
    console.warn('SafariTrak could not read the saved appearance preference.', error);
  }
  document.documentElement.dataset.theme = theme === 'light' ? 'light' : 'dark';

  const toggle = document.createElement('button');
  toggle.type = 'button';
  toggle.className = 'auth-theme-toggle';
  toggle.innerHTML = '<i class="fa-solid fa-circle-half-stroke" aria-hidden="true"></i><span></span>';
  toggle.setAttribute('aria-label', 'Switch colour theme');
  document.body.append(toggle);

  const label = toggle.querySelector('span');
  const updateLabel = () => {
    label.textContent = document.documentElement.dataset.theme === 'light' ? 'Light theme' : 'Dark theme';
    toggle.title = document.documentElement.dataset.theme === 'light' ? 'Switch to dark theme' : 'Switch to light theme';
  };
  updateLabel();
  toggle.addEventListener('click', () => {
    const nextTheme = document.documentElement.dataset.theme === 'light' ? 'dark' : 'light';
    document.documentElement.dataset.theme = nextTheme;
    try {
      localStorage.setItem('safaritrak-theme', nextTheme);
    } catch (error) {
      console.warn('SafariTrak could not save the appearance preference.', error);
    }
    updateLabel();
  });
})();
