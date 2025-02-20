let currentRound = 0;
const MAX_ROUNDS = 10;
let currentCategory = 'female';
let selectedContestants = new Set();

// Загрузка данных при старте
document.addEventListener('DOMContentLoaded', () => {
    loadContestants();
    updateStats();
    setupPhotoPreview();
    updateCategoryButtons();
});

// Переключение категории
function switchCategory(category) {
    currentCategory = category;
    loadContestants();
    updateCategoryButtons();
}

// Обновление кнопок категорий
function updateCategoryButtons() {
    document.getElementById('femaleBtn').classList.toggle('active', currentCategory === 'female');
    document.getElementById('maleBtn').classList.toggle('active', currentCategory === 'male');
}

// Загрузка списка участников
function loadContestants() {
    const contestants = JSON.parse(localStorage.getItem('contestants')) || [];
    const filteredContestants = contestants.filter(c => c.gender === currentCategory);
    const container = document.getElementById('contestantsList');
    
    container.innerHTML = filteredContestants.map(contestant => `
        <div class="contestant-card ${selectedContestants.has(contestant.id) ? 'selected' : ''}">
            <div class="actions">
                <button class="action-button select" onclick="toggleSelect(${contestant.id})">
                    <i class="fas ${selectedContestants.has(contestant.id) ? 'fa-check-square' : 'fa-square'}"></i>
                </button>
                <button class="action-button edit" onclick="editContestant(${contestant.id})">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="action-button delete" onclick="deleteContestant(${contestant.id})">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
            ${contestant.mediaType === 'photo' ? 
                `<img src="${contestant.media}" alt="${contestant.name}">` :
                `<video controls style="width: 100%; height: 200px; object-fit: cover;">
                    <source src="${contestant.media}" type="video/mp4">
                </video>`
            }
            <h3>${contestant.name}</h3>
            <p>Рейтинг: ${Math.round(contestant.rating)}</p>
            <span class="media-type">
                <i class="fas fa-${contestant.mediaType === 'photo' ? 'image' : 'video'}"></i>
                ${contestant.mediaType === 'photo' ? 'Фото' : 'Видео'}
            </span>
        </div>
    `).join('');

    updateDeleteSelectedButton();
}

// Выбор/отмена выбора участника
function toggleSelect(id) {
    if (selectedContestants.has(id)) {
        selectedContestants.delete(id);
    } else {
        selectedContestants.add(id);
    }
    loadContestants();
}

// Выбор всех участников
function selectAllContestants() {
    const contestants = JSON.parse(localStorage.getItem('contestants')) || [];
    const filteredContestants = contestants.filter(c => c.gender === currentCategory);
    
    if (selectedContestants.size === filteredContestants.length) {
        selectedContestants.clear();
    } else {
        filteredContestants.forEach(c => selectedContestants.add(c.id));
    }
    
    loadContestants();
}

// Обновление кнопки удаления выбранных
function updateDeleteSelectedButton() {
    const deleteSelectedBtn = document.getElementById('deleteSelectedBtn');
    deleteSelectedBtn.style.display = selectedContestants.size > 0 ? 'inline-flex' : 'none';
}

// Удаление выбранных участников
function deleteSelectedContestants() {
    if (selectedContestants.size === 0) return;
    
    showConfirmModal(
        `Вы уверены, что хотите удалить ${selectedContestants.size} выбранных участников?`,
        () => {
            const contestants = JSON.parse(localStorage.getItem('contestants')) || [];
            const newContestants = contestants.filter(c => !selectedContestants.has(c.id));
            localStorage.setItem('contestants', JSON.stringify(newContestants));
            selectedContestants.clear();
            loadContestants();
            updateStats();
        }
    );
}

// Удаление всех участников
function deleteAllContestants() {
    const contestants = JSON.parse(localStorage.getItem('contestants')) || [];
    const categoryContestants = contestants.filter(c => c.gender === currentCategory);
    
    if (categoryContestants.length === 0) return;
    
    showConfirmModal(
        `Вы уверены, что хотите удалить ВСЕХ участников категории "${currentCategory === 'female' ? 'Девушки' : 'Парни'}"?`,
        () => {
            const newContestants = contestants.filter(c => c.gender !== currentCategory);
            localStorage.setItem('contestants', JSON.stringify(newContestants));
            selectedContestants.clear();
            loadContestants();
            updateStats();
        }
    );
}

// Обновление статистики
function updateStats() {
    const contestants = JSON.parse(localStorage.getItem('contestants')) || [];
    const totalVotes = parseInt(localStorage.getItem('totalVotes')) || 0;
    const roundNumber = parseInt(localStorage.getItem('roundNumber')) || 0;
    
    document.getElementById('totalContestants').textContent = contestants.length;
    document.getElementById('totalVotes').textContent = totalVotes;
    document.getElementById('currentRound').textContent = roundNumber;
    document.getElementById('activeVotes').textContent = Math.max(0, 10 - roundNumber);
}

// Предпросмотр фотографии
function setupPhotoPreview() {
    const photoInput = document.getElementById('photo');
    const preview = document.getElementById('photoPreview');
    
    photoInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.style.backgroundImage = `url(${e.target.result})`;
                preview.textContent = '';
            };
            reader.readAsDataURL(file);
        }
    });
}

// Добавление нового участника
document.getElementById('addContestantForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const name = document.getElementById('name').value;
    const gender = document.getElementById('gender').value;
    const photoFile = document.getElementById('photo').files[0];
    
    if (photoFile) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const contestants = JSON.parse(localStorage.getItem('contestants')) || [];
            const newContestant = {
                id: Date.now(),
                name: name,
                gender: gender,
                image: e.target.result,
                rating: 1500
            };
            
            contestants.push(newContestant);
            localStorage.setItem('contestants', JSON.stringify(contestants));
            
            if (gender === currentCategory) {
                loadContestants();
            }
            updateStats();
            this.reset();
            document.getElementById('photoPreview').style.backgroundImage = '';
            document.getElementById('photoPreview').textContent = 'Предпросмотр фото';
        }.bind(this);
        
        reader.readAsDataURL(photoFile);
    }
});

// Редактирование участника
function editContestant(id) {
    const contestants = JSON.parse(localStorage.getItem('contestants')) || [];
    const contestant = contestants.find(c => c.id === id);
    
    if (contestant) {
        document.getElementById('name').value = contestant.name;
        document.getElementById('gender').value = contestant.gender;
        document.getElementById('photoPreview').style.backgroundImage = `url(${contestant.image})`;
        document.getElementById('addContestantForm').dataset.editId = id;
        
        // Изменяем текст кнопки
        const submitButton = document.querySelector('#addContestantForm button[type="submit"]');
        submitButton.innerHTML = '<i class="fas fa-save"></i> Сохранить';
    }
}

// Удаление участника
function deleteContestant(id) {
    const contestants = JSON.parse(localStorage.getItem('contestants')) || [];
    const contestant = contestants.find(c => c.id === id);
    
    if (contestant) {
        showConfirmModal(
            `Вы уверены, что хотите удалить участника "${contestant.name}"?`,
            () => {
                const newContestants = contestants.filter(c => c.id !== id);
                localStorage.setItem('contestants', JSON.stringify(newContestants));
                loadContestants();
                updateStats();
            }
        );
    }
}

// Сброс раундов
function resetRounds() {
    showConfirmModal(
        'Вы уверены, что хотите сбросить все раунды? Это действие нельзя отменить.',
        () => {
            localStorage.setItem('roundNumber', '0');
            localStorage.setItem('totalVotes', '0');
            const contestants = JSON.parse(localStorage.getItem('contestants')) || [];
            contestants.forEach(c => c.rating = 1500);
            localStorage.setItem('contestants', JSON.stringify(contestants));
            loadContestants();
            updateStats();
        }
    );
}

// Начало нового раунда
function startNewRound() {
    const roundNumber = parseInt(localStorage.getItem('roundNumber')) || 0;
    
    if (roundNumber >= 10) {
        alert('Все раунды уже завершены!');
        return;
    }
    
    localStorage.setItem('roundNumber', (roundNumber + 1).toString());
    updateStats();
}

// Модальное окно подтверждения
function showConfirmModal(message, callback) {
    const modal = document.getElementById('confirmModal');
    document.getElementById('confirmMessage').textContent = message;
    modal.style.display = 'block';
    modal.dataset.callback = callback.toString();
}

function confirmAction() {
    const modal = document.getElementById('confirmModal');
    const callback = new Function('return ' + modal.dataset.callback)();
    callback();
    closeModal('confirmModal');
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// Загрузка данных при открытии страницы
document.addEventListener('DOMContentLoaded', async () => {
    await loadSubmissions();
    loadContestants();
    updateStats();
    setupPhotoPreview();
    updateCategoryButtons();
});

// Загрузка предложенных участников
async function loadSubmissions() {
    const submissions = JSON.parse(localStorage.getItem('submissions') || '[]');
    const container = document.getElementById('submissionsList');
    
    if (submissions.length === 0) {
        container.innerHTML = '<p style="text-align: center; color: #666;">Нет предложенных участников</p>';
        return;
    }
    
    container.innerHTML = submissions.map(submission => `
        <div class="submission-card" data-id="${submission.id}">
            ${submission.mediaType === 'photo' ? 
                `<img src="${submission.media}" alt="${submission.name}">` :
                `<video controls style="width: 100%; height: 200px; object-fit: cover;">
                    <source src="${submission.media}" type="video/mp4">
                </video>`
            }
            <div class="submission-info">
                <h3>${submission.name}</h3>
                <span class="media-type">
                    <i class="fas fa-${submission.mediaType === 'photo' ? 'image' : 'video'}"></i>
                    ${submission.mediaType === 'photo' ? 'Фото' : 'Видео'}
                </span>
            </div>
            <div class="submission-actions">
                <button onclick="approveSubmission(${submission.id})" class="approve-btn">
                    <i class="fas fa-check"></i> Одобрить
                </button>
                <button onclick="rejectSubmission(${submission.id})" class="reject-btn">
                    <i class="fas fa-times"></i> Отклонить
                </button>
            </div>
        </div>
    `).join('');
}

// Одобрение предложенного участника
function approveSubmission(id) {
    const submissions = JSON.parse(localStorage.getItem('submissions') || '[]');
    const submission = submissions.find(s => s.id === id);
    
    if (submission) {
        const contestants = JSON.parse(localStorage.getItem('contestants') || '[]');
        contestants.push({
            id: Date.now(),
            name: submission.name,
            mediaType: submission.mediaType,
            media: submission.media,
            rating: 1500,
            gender: 'female' // По умолчанию
        });
        
        localStorage.setItem('contestants', JSON.stringify(contestants));
        
        // Удаляем из предложенных
        const newSubmissions = submissions.filter(s => s.id !== id);
        localStorage.setItem('submissions', JSON.stringify(newSubmissions));
        
        loadSubmissions();
        loadContestants();
        updateStats();
    }
}

// Отклонение предложенного участника
function rejectSubmission(id) {
    const submissions = JSON.parse(localStorage.getItem('submissions') || '[]');
    const newSubmissions = submissions.filter(s => s.id !== id);
    localStorage.setItem('submissions', JSON.stringify(newSubmissions));
    loadSubmissions();
}

// Загрузка активных участников
async function loadActiveContestants() {
    try {
        const response = await fetch('/api/active-contestants');
        const contestants = await response.json();
        
        const container = document.getElementById('activeContestants');
        container.innerHTML = '';
        
        contestants.forEach(contestant => {
            const contestantElement = createContestantElement(contestant);
            container.appendChild(contestantElement);
        });
    } catch (error) {
        console.error('Error loading contestants:', error);
    }
}

// Создание элемента участника
function createContestantElement(contestant) {
    const div = document.createElement('div');
    div.className = 'contestant-card';
    div.innerHTML = `
        <img src="${contestant.photoUrl}" alt="${contestant.name}">
        <div class="contestant-info">
            <h3>${contestant.name}</h3>
            <p>Рейтинг: ${contestant.rating}</p>
        </div>
    `;
    return div;
}

// Обновление отображения текущего раунда
function updateRoundDisplay() {
    document.getElementById('currentRound').textContent = currentRound;
    
    if (currentRound >= MAX_ROUNDS) {
        document.getElementById('startRoundBtn').textContent = 'Начать финальное голосование';
    }
}

// Начало финального голосования
async function startFinalVoting() {
    try {
        const response = await fetch('/api/start-final-voting', {
            method: 'POST'
        });
        
        if (response.ok) {
            alert('Финальное голосование началось!');
            window.location.href = 'index.html';
        }
    } catch (error) {
        console.error('Error starting final voting:', error);
    }
} 