(() => {
  let theme = 'dark';
  try {
    theme = localStorage.getItem('safaritrak-theme') || theme;
  } catch (error) {
    console.warn('SafariTrak could not read the saved appearance preference.', error);
  }
  document.documentElement.dataset.theme = theme === 'light' ? 'light' : 'dark';

  const backgroundPhotos = [
    'assets/images/safaritrak-auth-01.jpg',
    'assets/images/safaritrak-auth-02.jpg',
    'assets/images/safaritrak-auth-03.jpg',
    'assets/images/safaritrak-auth-04.jpg',
    'assets/images/safaritrak-auth-05.jpg',
  ];
  const photoStorageKey = 'safaritrak-auth-background-index';
  const photoRotationIntervalMs = 120000;
  let photoIndex = Math.floor(Math.random() * backgroundPhotos.length);
  try {
    const savedPhotoIndex = Number.parseInt(localStorage.getItem(photoStorageKey) || '', 10);
    if (Number.isInteger(savedPhotoIndex) && savedPhotoIndex >= 0 && savedPhotoIndex < backgroundPhotos.length) {
      photoIndex = (savedPhotoIndex + 1) % backgroundPhotos.length;
    }
  } catch (error) {
    console.warn('SafariTrak could not read the saved auth background preference.', error);
  }

  const background = document.createElement('div');
  background.className = 'auth-background-rotator';
  background.setAttribute('aria-hidden', 'true');
  const slides = Array.from({ length: 2 }, () => {
    const image = document.createElement('img');
    image.alt = '';
    image.decoding = 'async';
    image.draggable = false;
    background.append(image);
    return image;
  });
  let activeSlide = 0;
  slides[activeSlide].classList.add('is-active');
  slides[activeSlide].addEventListener('error', () => {
    console.error(`SafariTrak could not load auth background image "${backgroundPhotos[photoIndex]}".`);
  }, { once: true });
  slides[activeSlide].src = backgroundPhotos[photoIndex];
  document.body.prepend(background);

  const savePhotoIndex = () => {
    try {
      localStorage.setItem(photoStorageKey, String(photoIndex));
    } catch (error) {
      console.warn('SafariTrak could not save the auth background preference.', error);
    }
  };
  savePhotoIndex();

  let photoTimer = 0;
  if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    const rotateBackground = () => {
      const nextIndex = (photoIndex + 1) % backgroundPhotos.length;
      const nextSlideIndex = 1 - activeSlide;
      const nextSlide = slides[nextSlideIndex];
      nextSlide.onload = () => {
        nextSlide.onload = null;
        nextSlide.onerror = null;
        requestAnimationFrame(() => {
          nextSlide.classList.add('is-active');
          slides[activeSlide].classList.remove('is-active');
          activeSlide = nextSlideIndex;
        });
        photoIndex = nextIndex;
        savePhotoIndex();
        photoTimer = window.setTimeout(rotateBackground, photoRotationIntervalMs);
      };
      nextSlide.onerror = () => {
        nextSlide.onload = null;
        nextSlide.onerror = null;
        console.error(`SafariTrak could not load auth background image "${backgroundPhotos[nextIndex]}".`);
        photoIndex = nextIndex;
        savePhotoIndex();
        photoTimer = window.setTimeout(rotateBackground, photoRotationIntervalMs);
      };
      nextSlide.src = backgroundPhotos[nextIndex];
    };
    photoTimer = window.setTimeout(rotateBackground, photoRotationIntervalMs);
    window.addEventListener('pagehide', () => window.clearTimeout(photoTimer), { once: true });
  }

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
