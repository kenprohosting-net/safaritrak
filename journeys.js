const journeyTabs = document.querySelectorAll('[data-tab-group="journeys"] .tab');
const journeyRows = document.querySelectorAll('#journeyList .journey-row');
const emptyState = document.getElementById('emptyState');

journeyTabs.forEach(tab => {
  tab.addEventListener('click', () => {
    journeyTabs.forEach(t => t.classList.remove('active'));
    tab.classList.add('active');
    
    const filter = tab.getAttribute('data-tab');
    let visibleCount = 0;

    journeyRows.forEach(row => {
      const show = filter === 'all' || row.getAttribute('data-status') === filter;
      row.style.display = show ? '' : 'none';
      if (show) visibleCount++;
    });

    if (emptyState) {
      emptyState.style.display = visibleCount === 0 ? 'block' : 'none';
    }
  });
});

document.addEventListener('click', (e) => {
  const row = e.target.closest('[data-open-modal]');
  if (!row) return;

  const modalId = row.getAttribute('data-open-modal');
  const targetModal = document.getElementById(modalId);
  if (targetModal) {
    targetModal.classList.add('active');
  }
});

document.addEventListener('click', (e) => {
  if (e.target.closest('[data-close-modal]') || e.target.classList.contains('modal-overlay')) {
    const activeModal = e.target.closest('.modal-overlay');
    if (activeModal) {
      activeModal.classList.remove('active');
    }
  }
});