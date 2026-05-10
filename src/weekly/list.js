/*
  Requirement: Populate the "Weekly Course Breakdown" list page.

  Instructions:
  1. Link this file to `list.html` using:
     <script src="list.js" defer></script>

  2. In `list.html`, add an `id="week-list-section"` to the
     <section> element that will contain the weekly articles.

  3. Implement the TODOs below.
*/

// --- Element Selections ---
// TODO: Select the section for the week list ('#week-list-section').

const listSection = document.querySelector('#week-list-section');


// --- Functions ---

/**
 * TODO: Implement the createWeekArticle function.
 * It takes one week object {id, title, startDate, description}.
 * It should return an <article> element matching the structure in `list.html`.
 * - The "View Details & Discussion" link's `href` MUST be set to `details.html?id=${id}`.
 * (This is how the detail page will know which week to load).
 */
function createWeekArticle(week) {

  const article = document.createElement('article');

  const h2 = document.createElement('h2');
  h2.textContent = week.title;

  const startDate = document.createElement('p');
  startDate.textContent = week.start_date;

  const description = document.createElement('p');
  description.textContent = week.description;

  const link = document.createElement('a');
  link.href = `details.html?id=${week.id}`;
  link.textContent = 'View Details & Discussion';

  article.appendChild(h2);
  article.appendChild(startDate);
  article.appendChild(description);
  article.appendChild(link);

  return article;
}

/**
 * TODO: Implement the loadWeeks function.
 * This function needs to be 'async'.
 * It should:
 * 1. Use `fetch()` to get data from 'weeks.json'.
 * 2. Parse the JSON response into an array.
 * 3. Clear any existing content from `listSection`.
 * 4. Loop through the weeks array. For each week:
 *    - Call `createWeekArticle()`.
 *    - Append the returned <article> element to `listSection`.
 */
async function loadWeeks() {

  const response = await fetch('./api/index.php');

const result = await response.json();

const weeks = Array.isArray(result)
  ? result
  : result.data;

listSection.innerHTML = '';

weeks.forEach(week => {
    const article = createWeekArticle(week);
    listSection.appendChild(article);
  });
}

// --- Details Page Functions ---

/**
 * Extract the week ID from the URL query string.
 * Returns the value of the 'id' parameter.
 */
function getWeekIdFromURL() {
  const params = new URLSearchParams(window.location.search);
  return params.get('id');
}

let currentWeekId = null;

/**
 * Render week details into the page.
 * Sets title, start_date, description, and populates the links list.
 */
function renderWeekDetails(week) {
  const titleElement = document.querySelector('#week-title-detail');
  const startDateElement = document.querySelector('#week-start-date-detail');
  const descriptionElement = document.querySelector('#week-description-detail');
  const linksListElement = document.querySelector('#week-links-list');

  if (titleElement) titleElement.textContent = week.title;
  if (startDateElement) startDateElement.textContent = `Starts on: ${week.start_date}`;
  if (descriptionElement) descriptionElement.textContent = week.description;

  if (linksListElement) {
    linksListElement.innerHTML = '';
    if (week.links && Array.isArray(week.links)) {
      week.links.forEach(link => {
        const li = document.createElement('li');
        li.textContent = link;
        linksListElement.appendChild(li);
      });
    }
  }
}

/**
 * Create a comment article element.
 * Takes a comment object {text, author}.
 */
function createCommentArticle(comment) {
  const article = document.createElement('article');

  const p = document.createElement('p');
  p.textContent = comment.text;

  const footer = document.createElement('footer');
  footer.textContent = comment.author;

  article.appendChild(p);
  article.appendChild(footer);

  return article;
}

/**
 * Render comments to the comment list.
 */
function renderComments(comments) {
  const commentList = document.querySelector('#comment-list');
  if (!commentList) return;

  commentList.innerHTML = '';
  if (Array.isArray(comments)) {
    comments.forEach(comment => {
      const article = createCommentArticle(comment);
      commentList.appendChild(article);
    });
  }
}

/**
 * Handle adding a new comment.
 */
function handleAddComment(event) {
  event.preventDefault();

  const textarea = document.querySelector('#comment-textarea') || document.querySelector('#new-comment');
  if (!textarea || !textarea.value.trim()) {
    return;
  }

  const commentText = textarea.value;

  fetch(`./api/index.php?action=comment&week_id=${currentWeekId}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ text: commentText })
  })
    .then(response => response.json())
    .then(data => {
      textarea.value = '';
      // Refresh comments
      loadComments();
    })
    .catch(error => console.error('Error posting comment:', error));
}

/**
 * Load comments for the current week.
 */
async function loadComments() {
  if (!currentWeekId) return;

  try {
    const response = await fetch(`./api/index.php?action=comments&week_id=${currentWeekId}`);
    const comments = await response.json();
    renderComments(Array.isArray(comments) ? comments : comments.data || []);
  } catch (error) {
    console.error('Error loading comments:', error);
  }
}

/**
 * Initialize the details page.
 * Fetches week details and comments, and sets up event listeners.
 */
async function initializePage() {
  currentWeekId = getWeekIdFromURL();
  if (!currentWeekId) return;

  try {
    // Fetch week details
    const weekResponse = await fetch(`./api/index.php?id=${currentWeekId}`);
    const week = await weekResponse.json();
    renderWeekDetails(week);

    // Fetch comments
    const commentsResponse = await fetch(`./api/index.php?action=comments&week_id=${currentWeekId}`);
    const comments = await commentsResponse.json();
    renderComments(Array.isArray(comments) ? comments : comments.data || []);

    // Set up comment form listener
    const commentForm = document.querySelector('#comment-form');
    if (commentForm) {
      commentForm.addEventListener('submit', handleAddComment);
    }
  } catch (error) {
    console.error('Error initializing page:', error);
  }
}

/**
 * Create a table row for a week.
 */
function createWeekRow(week) {
  const tr = document.createElement('tr');

  const titleTd = document.createElement('td');
  titleTd.textContent = week.title;

  const startDateTd = document.createElement('td');
  startDateTd.textContent = week.start_date;

  const descriptionTd = document.createElement('td');
  descriptionTd.textContent = week.description;

  const actionsTd = document.createElement('td');

  const editBtn = document.createElement('button');
  editBtn.classList.add('edit-btn');
  editBtn.textContent = 'Edit';
  editBtn.setAttribute('data-id', week.id);

  const deleteBtn = document.createElement('button');
  deleteBtn.classList.add('delete-btn');
  deleteBtn.textContent = 'Delete';
  deleteBtn.setAttribute('data-id', week.id);

  actionsTd.appendChild(editBtn);
  actionsTd.appendChild(deleteBtn);

  tr.appendChild(titleTd);
  tr.appendChild(startDateTd);
  tr.appendChild(descriptionTd);
  tr.appendChild(actionsTd);

  return tr;
}

/**
 * Render weeks to the table body.
 */
function renderTable(weeks) {
  const tbody = document.querySelector('table tbody');
  if (!tbody) return;

  tbody.innerHTML = '';
  weeks.forEach(week => {
    const row = createWeekRow(week);
    tbody.appendChild(row);
  });
}

/**
 * Handle adding a new week.
 */
function handleAddWeek(event) {
  event.preventDefault();

  const titleInput = document.querySelector('#week-title');
  const startDateInput = document.querySelector('#week-start-date');
  const descriptionInput = document.querySelector('#week-description');
  const linksInput = document.querySelector('#week-links');

  if (!titleInput || !startDateInput || !descriptionInput) return;

  const title = titleInput.value;
  const startDate = startDateInput.value;
  const description = descriptionInput.value;
  const linksText = linksInput ? linksInput.value : '';
  const links = linksText ? linksText.split('\n').filter(link => link.trim()) : [];

  fetch('./api/index.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ title, start_date: startDate, description, links })
  })
    .then(response => response.json())
    .then(data => {
      titleInput.value = '';
      startDateInput.value = '';
      descriptionInput.value = '';
      if (linksInput) linksInput.value = '';
      loadAndInitialize();
    })
    .catch(error => console.error('Error adding week:', error));
}

/**
 * Handle table button clicks.
 */
function handleTableClick(event) {
  const target = event.target;

  if (target.classList.contains('delete-btn')) {
    const weekId = target.getAttribute('data-id');
    fetch(`./api/index.php?id=${weekId}`, { method: 'DELETE' })
      .then(response => response.json())
      .then(data => loadAndInitialize())
      .catch(error => console.error('Error deleting week:', error));
  } else if (target.classList.contains('edit-btn')) {
    const weekId = target.getAttribute('data-id');
    const row = target.closest('tr');
    const titleTd = row.querySelector('td:nth-child(1)');
    const startDateTd = row.querySelector('td:nth-child(2)');
    const descriptionTd = row.querySelector('td:nth-child(3)');

    const titleInput = document.querySelector('#week-title');
    const startDateInput = document.querySelector('#week-start-date');
    const descriptionInput = document.querySelector('#week-description');

    if (titleInput && titleTd) titleInput.value = titleTd.textContent;
    if (startDateInput && startDateTd) startDateInput.value = startDateTd.textContent;
    if (descriptionInput && descriptionTd) descriptionInput.value = descriptionTd.textContent;
  }
}

/**
 * Load and initialize the admin page.
 */
async function loadAndInitialize() {
  try {
    const response = await fetch('./api/index.php');
    const result = await response.json();
    const weeks = Array.isArray(result) ? result : result.data;
    renderTable(weeks);

    const weekForm = document.querySelector('#week-form');
    if (weekForm) {
      weekForm.addEventListener('submit', handleAddWeek);
    }

    const table = document.querySelector('table');
    if (table) {
      table.addEventListener('click', handleTableClick);
    }
  } catch (error) {
    console.error('Error loading weeks:', error);
  }
}

// --- Initial Page Load ---
// Check if this is the list page or details page
if (document.querySelector('#week-list-section')) {
  loadWeeks();
} else if (document.querySelector('#week-title-detail')) {
  initializePage();
} else if (document.querySelector('table')) {
  loadAndInitialize();
}
