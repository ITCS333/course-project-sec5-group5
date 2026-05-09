/*
  Requirement: Make the "Manage Assignments" page interactive.

  Instructions:
  1. This file is already linked to `admin.html` via:
         <script src="admin.js" defer></script>

  2. In `admin.html`:
     - The form has id="assignment-form".
     - The submit button has id="add-assignment".
     - The <tbody> has id="assignments-tbody".
     - Columns rendered per row:
       Title | Due Date | Description | Actions.

  3. Implement the TODOs below.

  API base URL: ./api/index.php
  All requests and responses use JSON.
  Successful list response shape: { success: true, data: [ ...assignment objects ] }
  Each assignment object shape:
    {
      id:          number,   // integer primary key from the assignments table
      title:       string,
      due_date:    string,   // "YYYY-MM-DD" — matches the SQL column name
      description: string,
      files:       string[]  // decoded array of URL strings
    }
*/

// --- Global Data Store ---
// Holds the assignments currently displayed in the table.
let assignments = [];

const assignmentForm = document.getElementById('assignment-form');
const assignmentsTbody = document.getElementById('assignments-tbody');
const submitBtn = document.getElementById('add-assignment');

function createAssignmentRow(assignment) {
   const tr = document.createElement('tr');
   tr.innerHTML = `
<td>${assignment.title}</td>
<td>${assignment.due_date}</td>
<td>${assignment.description}</td>
<td>
<button class="edit-btn" data-id="${assignment.id}">Edit</button>
<button class="delete-btn" data-id="${assignment.id}">Delete</button>
</td>
   `;
   return tr;
}

function renderTable() {
   assignmentsTbody.innerHTML = "";
   assignments.forEach(assignment => {
       const row = createAssignmentRow(assignment);
       assignmentsTbody.appendChild(row);
   });
}

async function handleAddAssignment(event) {
   event.preventDefault();

   const title = document.getElementById('assignment-title').value;
   const due_date = document.getElementById('assignment-due-date').value;
   const description = document.getElementById('assignment-description').value;
   const filesRaw = document.getElementById('assignment-files').value;
   const files = filesRaw.split('\n').map(f => f.trim()).filter(f => f !== "");

   const editId = submitBtn.getAttribute('data-edit-id');

   if (editId) {
       await handleUpdateAssignment(parseInt(editId), { title, due_date, description, files });
   } else {
       const response = await fetch('./api/index.php', {
           method: 'POST',
           headers: { 'Content-Type': 'application/json' },
           body: JSON.stringify({ title, due_date, description, files })
       });
       const result = await response.json();

       if (result.success) {
           assignments.push({ id: result.id, title, due_date, description, files });
           renderTable();
           assignmentForm.reset();
       }
   }
}

async function handleUpdateAssignment(id, fields) {
   const response = await fetch('./api/index.php', {
       method: 'PUT',
       headers: { 'Content-Type': 'application/json' },
       body: JSON.stringify({ id, ...fields })
   });
   const result = await response.json();

   if (result.success) {
       const index = assignments.findIndex(a => a.id === id);
       if (index !== -1) {
           assignments[index] = { id, ...fields };
       }
       renderTable();
       assignmentForm.reset();
       submitBtn.textContent = "Add Assignment";
       submitBtn.removeAttribute('data-edit-id');
   }
}

async function handleTableClick(event) {
   const target = event.target;
   const id = parseInt(target.dataset.id);

   if (target.classList.contains('delete-btn')) {
       const response = await fetch(`./api/index.php?id=${id}`, { method: 'DELETE' });
       const result = await response.json();
       if (result.success) {
           assignments = assignments.filter(a => a.id !== id);
           renderTable();
       }
   } else if (target.classList.contains('edit-btn')) {
       const assignment = assignments.find(a => a.id === id);
       if (assignment) {
           document.getElementById('assignment-title').value = assignment.title;
           document.getElementById('assignment-due-date').value = assignment.due_date;
           document.getElementById('assignment-description').value = assignment.description;
           document.getElementById('assignment-files').value = assignment.files.join('\n');

           submitBtn.textContent = "Update Assignment";
           submitBtn.setAttribute('data-edit-id', id);
       }
   }
}

async function loadAndInitialize() {
   try {
       const response = await fetch('./api/index.php');
       const result = await response.json();
       if (result.success) {
           assignments = result.data;
           renderTable();
       }
   } catch (error) {
       console.error("Failed to load assignments:", error);
   }

   assignmentForm.addEventListener('submit', handleAddAssignment);
   assignmentsTbody.addEventListener('click', handleTableClick);
}

loadAndInitialize();
