// Загрузка данных при старте
document.addEventListener('DOMContentLoaded', async () => {
    await loadTournamentData();
    await loadContestants();
    await loadSubmissions();
    updateStats();
});

// Загрузка данных турнира
async function loadTournamentData() {
    try {
        const response = await fetch('/api/tournament.php', {
            headers: {
                'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
            }
        });
        const data = await response.json();
        
        if (data.success) {
            updateTournamentStatus(data);
            if (data.tournament) {
                updateBracket(data.tournament, data.round);
            }
        }
    } catch (error) {
        console.error('Error loading tournament data:', error);
        showNotification('Ошибка при загрузке данных турнира', 'error');
    }
}

// Обновление статуса турнира
function updateTournamentStatus(data) {
    const statusDiv = document.getElementById('tournamentStatus');
    const createBtn = document.getElementById('createTournamentBtn');
    const completeBtn = document.getElementById('completeTournamentBtn');
    
    if (!data.tournament) {
        statusDiv.className = 'tournament-status';
        statusDiv.innerHTML = `
            <i class="fas fa-circle"></i>
            <span>Нет активного турнира</span>
        `;
        createBtn.style.display = 'block';
        completeBtn.style.display = 'none';
        return;
    }
    
    const status = data.tournament.status;
    statusDiv.className = `tournament-status ${status}`;
    statusDiv.innerHTML = `
        <i class="fas fa-circle"></i>
        <span>${getStatusText(status)}</span>
    `;
    
    createBtn.style.display = status === 'completed' ? 'block' : 'none';
    completeBtn.style.display = status === 'active' ? 'block' : 'none';
}

// Получение текста статуса
function getStatusText(status) {
    switch (status) {
        case 'pending': return 'Ожидание начала';
        case 'active': return 'Турнир активен';
        case 'completed': return 'Турнир завершен';
        default: return 'Неизвестный статус';
    }
}

// Обновление турнирной сетки
function updateBracket(tournament, currentRound) {
    const rounds = ['round1Matches', 'round2Matches', 'round3Matches', 'round4Matches'];
    
    rounds.forEach((roundId, index) => {
        const roundDiv = document.getElementById(roundId);
        const roundNumber = index + 1;
        const isCurrentRound = roundNumber === tournament.current_round;
        const isCompletedRound = roundNumber < tournament.current_round;
        
        // Получаем матчи для раунда
        fetch(`/api/tournament.php?round=${roundNumber}`, {
            headers: {
                'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.matches) {
                roundDiv.innerHTML = data.matches.map(match => createMatchElement(match, isCurrentRound)).join('');
            }
        });
    });
}

// Создание элемента матча
function createMatchElement(match, isCurrentRound) {
    const requiredVotes = parseInt(document.getElementById('requiredVotes').value);
    return `
        <div class="bracket-match" data-match-id="${match.id}">
            <div class="bracket-contestant ${match.winner_id === match.contestant1_id ? 'winner' : ''}">
                <img src="${match.contestant1_photo}" alt="${match.contestant1_name}">
                <span>${match.contestant1_name}</span>
                <div class="bracket-votes">
                    <span class="votes-count">${match.contestant1_votes || 0}</span>
                    <span class="votes-required">/ ${requiredVotes}</span>
                </div>
            </div>
            <div class="bracket-contestant ${match.winner_id === match.contestant2_id ? 'winner' : ''}">
                <img src="${match.contestant2_photo}" alt="${match.contestant2_name}">
                <span>${match.contestant2_name}</span>
                <div class="bracket-votes">
                    <span class="votes-count">${match.contestant2_votes || 0}</span>
                    <span class="votes-required">/ ${requiredVotes}</span>
                </div>
            </div>
            ${isCurrentRound && match.status === 'active' ? createMatchActions(match) : ''}
        </div>
    `;
}

// Создание кнопок управления матчем
function createMatchActions(match) {
    return `
        <div class="match-actions">
            <button class="match-action" onclick="completeMatch(${match.id})">
                <i class="fas fa-check"></i> Завершить матч
            </button>
            <button class="match-action" onclick="resetMatch(${match.id})">
                <i class="fas fa-redo"></i> Сбросить
            </button>
        </div>
    `;
}

// Создание нового турнира
async function createTournament() {
    try {
        const requiredVotes = parseInt(document.getElementById('requiredVotes').value);
        const response = await fetch('/api/tournament.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
            },
            body: JSON.stringify({
                action: 'create_tournament',
                required_votes: requiredVotes
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('Турнир успешно создан');
            await loadTournamentData();
        } else {
            throw new Error(data.error);
        }
    } catch (error) {
        console.error('Error creating tournament:', error);
        showNotification(error.message, 'error');
    }
}

// Завершение турнира
async function completeTournament() {
    showConfirmModal(
        'Вы уверены, что хотите завершить текущий турнир?',
        async () => {
            try {
                const response = await fetch('/api/tournament.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
                    },
                    body: JSON.stringify({
                        action: 'complete_tournament'
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification('Турнир успешно завершен');
                    await loadTournamentData();
                } else {
                    throw new Error(data.error);
                }
            } catch (error) {
                console.error('Error completing tournament:', error);
                showNotification(error.message, 'error');
            }
        }
    );
}

// Завершение матча
async function completeMatch(matchId) {
    showConfirmModal(
        'Вы уверены, что хотите завершить этот матч?',
        async () => {
            try {
                const response = await fetch('/api/tournament.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
                    },
                    body: JSON.stringify({
                        action: 'complete_match',
                        match_id: matchId
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification('Матч успешно завершен');
                    await loadTournamentData();
                } else {
                    throw new Error(data.error);
                }
            } catch (error) {
                console.error('Error completing match:', error);
                showNotification(error.message, 'error');
            }
        }
    );
}

// Сброс матча
async function resetMatch(matchId) {
    showConfirmModal(
        'Вы уверены, что хотите сбросить результаты этого матча?',
        async () => {
            try {
                const response = await fetch('/api/tournament.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
                    },
                    body: JSON.stringify({
                        action: 'reset_match',
                        match_id: matchId
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification('Матч успешно сброшен');
                    await loadTournamentData();
                } else {
                    throw new Error(data.error);
                }
            } catch (error) {
                console.error('Error resetting match:', error);
                showNotification(error.message, 'error');
            }
        }
    );
}

// Обновление статистики
function updateStats() {
    fetch('/api/tournament.php?stats=true', {
        headers: {
            'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('totalVotes').textContent = data.total_votes;
            document.getElementById('activeMatches').textContent = data.active_matches;
            document.getElementById('completedRounds').textContent = data.completed_rounds;
        }
    })
    .catch(error => {
        console.error('Error updating stats:', error);
    });
}

// Показ уведомления
function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
        ${message}
    `;
    document.body.appendChild(notification);

    setTimeout(() => {
        notification.style.animation = 'slideInRight 0.3s ease-out reverse';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
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

// Загрузка участниц
async function loadContestants() {
    try {
        const response = await fetch('/api/contestants.php', {
            headers: {
                'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
            }
        });
        const data = await response.json();
        
        if (data.success) {
            const container = document.getElementById('contestantsList');
            container.innerHTML = data.contestants.map(contestant => `
                <div class="contestant-card">
                    <div class="card-media">
                        <img src="${contestant.photo}" alt="${contestant.name}">
                    </div>
                    <div class="card-content">
                        <h3>${contestant.name}</h3>
                        <div class="rating">
                            <i class="fas fa-star"></i>
                            Рейтинг: ${contestant.rating}
                        </div>
                        <div class="card-actions">
                            <button class="action-button" onclick="editContestant(${contestant.id})">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="action-button danger" onclick="deleteContestant(${contestant.id})">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `).join('');
        }
    } catch (error) {
        console.error('Error loading contestants:', error);
        showNotification('Ошибка при загрузке участниц', 'error');
    }
}

// Загрузка предложенных участниц
async function loadSubmissions() {
    try {
        const response = await fetch('/api/submissions.php', {
            headers: {
                'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
            }
        });
        const data = await response.json();
        
        if (data.success) {
            const container = document.getElementById('submissionsList');
            container.innerHTML = data.submissions.length ? data.submissions.map(submission => `
                <div class="submission-card">
                    <div class="card-media">
                        <img src="${submission.photo}" alt="${submission.name}">
                    </div>
                    <div class="card-content">
                        <h3>${submission.name}</h3>
                        <div class="card-actions">
                            <button class="action-button success" onclick="approveSubmission(${submission.id})">
                                <i class="fas fa-check"></i>
                            </button>
                            <button class="action-button danger" onclick="rejectSubmission(${submission.id})">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `).join('') : '<p class="empty-message">Нет новых предложений</p>';
        }
    } catch (error) {
        console.error('Error loading submissions:', error);
        showNotification('Ошибка при загрузке предложений', 'error');
    }
}

// Автоматическое обновление данных
setInterval(async () => {
    await loadTournamentData();
    updateStats();
}, 30000); // Каждые 30 секунд 