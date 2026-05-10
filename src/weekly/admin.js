let weeks = [];

const weekForm = document.getElementById('week-form');
const weeksTbody = document.getElementById('weeks-tbody');

function createWeekRow(week) {
  const row = document.createElement('tr');

  const titleCell = document.createElement('td');
  titleCell.textContent = week.title;
  row.appendChild(titleCell);

  const startDateCell = document.createElement('td');
  startDateCell.textContent = week.start_date;
  row.appendChild(startDateCell);

  const descCell = document.createElement('td');
  descCell.textContent = week.description;
  row.appendChild(descCell);

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
  row.appendChild(actionsCell);

  return row;
}

function renderTable() {
  weeksTbody.innerHTML = '';
  for (const week of weeks) {
    const row = createWeekRow(week);
    weeksTbody.appendChild(row);
  }
}

async function handleAddWeek(event) {
  event.preventDefault();

  const title = document.getElementById('week-title').value;
  const start_date = document.getElementById('week-start-date').value;
  const description = document.getElementById('week-description').value;
  const linksText = document.getElementById('week-links').value;
  const links = linksText.split('\n').filter(line => line.trim() !== '');

  const addButton = document.getElementById('add-week');

  if (addButton.hasAttribute('data-edit-id')) {
    const id = parseInt(addButton.dataset.editId);
    await handleUpdateWeek(id, { title, start_date, description, links });
    return;
  }

  const response = await fetch('./api/index.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ title, start_date, description, links })
  });

  const result = await response.json();

  if (result.success) {
    const newWeek = {
      id: result.id,
      title,
      start_date,
      description,
      links
    };
    weeks.push(newWeek);
    renderTable();
    weekForm.reset();
  }
}

async function handleUpdateWeek(id, fields) {
  const response = await fetch('./api/index.php', {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id, ...fields })
  });

  const result = await response.json();

  if (result.success) {
    const index = weeks.findIndex(week => week.id === id);
    if (index !== -1) {
      weeks[index] = { id, ...fields };
    }
    renderTable();
    weekForm.reset();
    const addButton = document.getElementById('add-week');
    addButton.textContent = 'Add Week';
    addButton.removeAttribute('data-edit-id');
  }
}

async function handleTableClick(event) {
  if (event.target.classList.contains('delete-btn')) {
    const id = parseInt(event.target.dataset.id);
    const response = await fetch(`./api/index.php?id=${id}`, { method: 'DELETE' });
    const result = await response.json();

    if (result.success) {
      weeks = weeks.filter(week => week.id !== id);
      renderTable();
    }
  }

  if (event.target.classList.contains('edit-btn')) {
    const id = parseInt(event.target.dataset.id);
    const week = weeks.find(w => w.id === id);

    if (week) {
      document.getElementById('week-title').value = week.title;
      document.getElementById('week-start-date').value = week.start_date;
      document.getElementById('week-description').value = week.description;
      document.getElementById('week-links').value = week.links.join('\n');

      const addButton = document.getElementById('add-week');
      addButton.textContent = 'Update Week';
      addButton.dataset.editId = id;
    }
  }
}

async function loadAndInitialize() {
  const response = await fetch('./api/index.php');
  const result = await response.json();

  if (result.success) {
    weeks = result.data;
    renderTable();
  }

  weekForm.addEventListener('submit', handleAddWeek);
  weeksTbody.addEventListener('click', handleTableClick);
}

loadAndInitialize();
