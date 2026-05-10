/*
  Requirement: Populate the weekly detail page and discussion forum.

  Instructions:
  1. Link this file to `details.html` using:
     <script src="details.js" defer></script>

  2. In `details.html`, add the following IDs:
     - To the <h1>: `id="week-title"`
     - To the start date <p>: `id="week-start-date"`
     - To the description <p>: `id="week-description"`
     - To the "Exercises & Resources" <ul>: `id="week-links-list"`
     - To the <div> for comments: `id="comment-list"`
     - To the "Ask a Question" <form>: `id="comment-form"`
     - To the <textarea>: `id="new-comment-text"`

  3. Implement the TODOs below.
*/

// --- Global Data Store ---
// These will hold the data related to *this* specific week.
let currentWeekId = null;
let currentComments = [];

// --- Element Selections ---
// TODO: Select all the elements you added IDs for in step 2.
const weekTitle = document.getElementById('week-title');
const weekStartDate = document.getElementById('week-start-date');
const weekDescription = document.getElementById('week-description');
const weekLinksList = document.getElementById('week-links-list');
const commentList = document.getElementById('comment-list');
const commentForm = document.getElementById('comment-form');
const newCommentText = document.getElementById('new-comment-text');

// --- Functions ---

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
  link.textContent = 'View';

  article.appendChild(h2);
  article.appendChild(startDate);
  article.appendChild(description);
  article.appendChild(link);

  return article;
}

async function loadWeeks() {
  const container = document.getElementById('week-list-section');

  container.innerHTML = '';

  const response = await fetch('./api/index.php');

  const weeksData = await response.json();

  weeksData.forEach(week => {
    const article = createWeekArticle(week);
    container.appendChild(article);
  });
}

/**
 * TODO: Implement the getWeekIdFromURL function.
 * It should:
 * 1. Get the query string from `window.location.search`.
 * 2. Use the `URLSearchParams` object to get the value of the 'id' parameter.
 * 3. Return the id.
 */
function getWeekIdFromURL() {
  const queryString = window.location.search;
  const urlParams = new URLSearchParams(queryString);
  const id = urlParams.get('id');
  return id;
}

/**
 * TODO: Implement the renderWeekDetails function.
 * It takes one week object.
 * It should:
 * 1. Set the `textContent` of `weekTitle` to the week's title.
 * 2. Set the `textContent` of `weekStartDate` to "Starts on: " + week's startDate.
 * 3. Set the `textContent` of `weekDescription`.
 * 4. Clear `weekLinksList` and then create and append `<li><a href="...">...</a></li>`
 * for each link in the week's 'links' array. The link's `href` and `textContent`
 * should both be the link URL.
 */
function renderWeekDetails(week) {
  weekTitle.textContent = week.title;
  weekStartDate.textContent = 'Starts on: ' + week.start_date;
  weekDescription.textContent = week.description;

  weekLinksList.innerHTML = '';

  // Check if week.links exists and is iterable before looping
  if (week.links && Array.isArray(week.links)) {
    for (const link of week.links) {
      const li = document.createElement('li');
      const a = document.createElement('a');

      a.href = link;
      a.textContent = link;

      li.appendChild(a);
      weekLinksList.appendChild(li);
    }
  }
}

/**
 * TODO: Implement the createCommentArticle function.
 * It takes one comment object {author, text}.
 * It should return an <article> element matching the structure in `details.html`.
 * (e.g., an <article> containing a <p> and a <footer>).
 */
function createCommentArticle(comment) {
  const article = document.createElement('article');

  const p = document.createElement('p');
  p.textContent = comment.text;

  const footer = document.createElement('footer');
  footer.textContent = 'Posted by: ' + comment.author;

  article.appendChild(p);
  article.appendChild(footer);

  return article;
}

/**
 * TODO: Implement the renderComments function.
 * It should:
 * 1. Clear the `commentList`.
 * 2. Loop through the global `currentComments` array.
 * 3. For each comment, call `createCommentArticle()`, and
 * append the resulting <article> to `commentList`.
 */
function renderComments() {
  commentList.innerHTML = '';

  for (const comment of currentComments) {
    const article = createCommentArticle(comment);
    commentList.appendChild(article);
  }
}

/**
 * TODO: Implement the handleAddComment function.
 * This is the event handler for the `commentForm` 'submit' event.
 * It should:
 * 1. Prevent the form's default submission.
 * 2. Get the text from `newCommentText.value`.
 * 3. If the text is empty, return.
 * 4. Create a new comment object: { author: 'Student', text: commentText }
 * (For this exercise, 'Student' is a fine hardcoded author).
 * 5. Add the new comment to the global `currentComments` array (in-memory only).
 * 6. Call `renderComments()` to refresh the list.
 * 7. Clear the `newCommentText` textarea.
 */
async function handleAddComment(event) {
  event.preventDefault();

  // Check if newCommentText exists before accessing value
  if (!newCommentText) return;

  const commentText = newCommentText.value.trim();

  if (!commentText) return;

  const newComment = {
    week_id: currentWeekId,
    author: 'Student',
    text: commentText
  };

  await fetch('./api/index.php?action=comment', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify(newComment)
  });

  currentComments.push({
    author: 'Student',
    text: commentText
  });

  renderComments();

  newCommentText.value = '';
}

/**
 * TODO: Implement an `initializePage` function.
 * This function needs to be 'async'.
 * It should:
 * 1. Get the `currentWeekId` by calling `getWeekIdFromURL()`.
 * 2. If no ID is found, set `weekTitle.textContent = "Week not found."` and stop.
 * 3. `fetch` both 'weeks.json' and 'week-comments.json' (you can use `Promise.all`).
 * 4. Parse both JSON responses.
 * 5. Find the correct week from the weeks array using the `currentWeekId`.
 * 6. Get the correct comments array from the comments object using the `currentWeekId`.
 * Store this in the global `currentComments` variable. (If no comments exist, use an empty array).
 * 7. If the week is found:
 * - Call `renderWeekDetails()` with the week object.
 * - Call `renderComments()` to show the initial comments.
 * - Add the 'submit' event listener to `commentForm` (calls `handleAddComment`).
 * 8. If the week is not found, display an error in `weekTitle`.
 */
async function initializePage() {
  currentWeekId = getWeekIdFromURL();

  if (!currentWeekId) {
    weekTitle.textContent = 'Week not found.';
    return;
  }

  try {
    const [weekResponse, commentsResponse] = await Promise.all([
      fetch(`./api/index.php?id=${currentWeekId}`),
      fetch(`./api/index.php?action=comments&week_id=${currentWeekId}`)
    ]);

    const weekData = await weekResponse.json();
    const commentsData = await commentsResponse.json();

    const week = Array.isArray(weekData)
      ? weekData[0]
      : weekData;

    currentComments = Array.isArray(commentsData)
      ? commentsData
      : [];

    if (week) {
      renderWeekDetails(week);
      renderComments();

      commentForm.addEventListener('submit', handleAddComment);
    } else {
      weekTitle.textContent = 'Week not found.';
    }

  } catch (error) {
    weekTitle.textContent = 'Error loading the data.';
    console.error('Initialization not successful:', error);
  }
}

// --- Initial Page Load ---
initializePage();
