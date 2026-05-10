let weeks = [];

const weekForm = document.getElementById('week-form');
const weeksTbody = document.getElementById('weeks-tbody');
const addButton = document.getElementById('add-week');

/* =========================
   API FUNCTIONS
========================= */

async function fetchWeeks() {
  const res = await fetch('./api/index.php');
  const data = await res.json();
  return data;
}

async function createWeekAPI(week) {
  return await fetch('./api/index.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(week)
  });
}

async function updateWeekAPI(week) {
  return await fetch('./api/index.php', {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(week)
  });
}

async function deleteWeekAPI(id) {
  return await fetch(`./api/index.php?id=${id}`, {
    method: 'DELETE'
  });
}

/* =========================
   UI RENDER
========================= */

function createWeekRow(week) {
  const row = document.createElement('tr');

  const titleCell = document.createElement('td');
  titleCell.textContent = week.title;

  const descCell = document.createElement('td');
  descCell.textContent = week.description;

  const actionsCell = document.createElement('td');

  const editBtn = document.createElement('button');
  editBtn.textContent = 'Edit';
  editBtn.className = 'edit-btn';
  editBtn.dataset.id = week.id;

  const deleteBtn = document.createElement('button');
  deleteBtn.textContent = 'Delete';
  deleteBtn.className = 'delete-btn';
  deleteBtn.dataset.id = week.id;

  actionsCell.appendChild(editBtn);
  actionsCell.appendChild(deleteBtn);

  row.appendChild(titleCell);
  row.appendChild(descCell);
  row.appendChild(actionsCell);

  return row;
}

function renderWeeks() {
  weeksTbody.innerHTML = '';
  weeks.forEach(week => {
    weeksTbody.appendChild(createWeekRow(week));
  });
}

/* =========================
   FORM HANDLERS
========================= */

function getFormData() {
  return {
    title: document.getElementById('week-title').value,
    start_date: document.getElementById('week-start-date').value,
    description: document.getElementById('week-description').value,
    links: document.getElementById('week-links').value
      .split('\n')
      .filter(l => l.trim() !== '')
  };
}

function fillForm(week) {
  document.getElementById('week-title').value = week.title;
  document.getElementById('week-start-date').value = week.start_date;
  document.getElementById('week-description').value = week.description;
  document.getElementById('week-links').value = week.links.join('\n');
}

/* =========================
   CRUD LOGIC
========================= */

async function addOrUpdateWeek(e) {
  e.preventDefault();

  const data = getFormData();

  if (addButton.dataset.editId) {
    const id = parseInt(addButton.dataset.editId);
    await updateWeekAPI({ id, ...data });

    weeks = weeks.map(w => (w.id === id ? { id, ...data } : w));

    addButton.textContent = 'Add Week';
    delete addButton.dataset.editId;
  } else {
    const res = await createWeekAPI(data);
    const result = await res.json();

    if (result.success) {
      weeks.push({ id: result.id, ...data });
    }
  }

  weekForm.reset();
  renderWeeks();
}

async function handleTableClick(e) {
  const id = parseInt(e.target.dataset.id);

  if (e.target.classList.contains('delete-btn')) {
    const res = await deleteWeekAPI(id);
    const result = await res.json();

    if (result.success) {
      weeks = weeks.filter(w => w.id !== id);
      renderWeeks();
    }
  }

  if (e.target.classList.contains('edit-btn')) {
    const week = weeks.find(w => w.id === id);

    if (week) {
      fillForm(week);
      addButton.textContent = 'Update Week';
      addButton.dataset.editId = id;
    }
  }
}

/* =========================
   INIT
========================= */

async function init() {
  const result = await fetchWeeks();

  if (result.success) {
    weeks = result.data;
    renderWeeks();
  }

  weekForm.addEventListener('submit', addOrUpdateWeek);
  weeksTbody.addEventListener('click', handleTableClick);
}

init();
