// Форматирование номера телефона
function formatPhoneNumber(value) {
    if (!value) return value;
    value = value.replace(/\D/g, '');
    if (value.length > 10) value = value.slice(0, 10);
    
    let formattedValue = '';
    if (value.length >= 1) {
        formattedValue = `(${value.slice(0, 3)}`;
        if (value.length >= 4) {
            formattedValue += `) ${value.slice(3, 6)}`;
            if (value.length >= 7) {
                formattedValue += `-${value.slice(6, 8)}`;
                if (value.length >= 9) {
                    formattedValue += `-${value.slice(8, 10)}`;
                }
            }
        }
    }
    return formattedValue;
}

// Маска для номера телефона
document.getElementById('phone').addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    e.target.value = formatPhoneNumber(value);
});

// Обработка формы телефона
document.getElementById('phoneForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const phoneInput = document.getElementById('phone');
    const phone = '+7' + phoneInput.value.replace(/\D/g, '');
    
    if (phone.length !== 12) {
        showError('Пожалуйста, введите корректный номер телефона');
        return;
    }
    
    try {
        const response = await fetch('/api/auth/send-code.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ phone })
        });
        
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.error || 'Ошибка отправки кода');
        }
        
        // Сохраняем телефон для последующей проверки
        localStorage.setItem('auth_phone', phone);
        document.getElementById('confirmedPhone').textContent = phoneInput.value;
        
        // Показываем форму ввода кода
        document.getElementById('phoneStep').classList.add('hidden');
        document.getElementById('codeStep').classList.remove('hidden');
        startTimer();
        setupCodeInputs();
        
    } catch (error) {
        showError('Произошла ошибка при отправке кода. Пожалуйста, попробуйте позже.');
        console.error('Error:', error);
    }
});

// Настройка полей ввода кода
function setupCodeInputs() {
    const inputs = document.querySelectorAll('.code-inputs input');
    
    inputs.forEach((input, index) => {
        // Очищаем значения
        input.value = '';
        
        // Автофокус на следующее поле
        input.addEventListener('input', function(e) {
            if (e.target.value.length === 1) {
                if (index < inputs.length - 1) {
                    inputs[index + 1].focus();
                } else {
                    // Если это последнее поле и все поля заполнены, отправляем форму
                    if (Array.from(inputs).every(input => input.value.length === 1)) {
                        document.getElementById('codeForm').dispatchEvent(new Event('submit'));
                    }
                }
            }
        });
        
        // Обработка удаления
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Backspace' && !e.target.value && index > 0) {
                inputs[index - 1].focus();
            }
        });
        
        // Только цифры
        input.addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/\D/g, '');
        });
        
        // Вставка кода целиком
        input.addEventListener('paste', function(e) {
            e.preventDefault();
            const pastedText = (e.clipboardData || window.clipboardData).getData('text');
            const digits = pastedText.replace(/\D/g, '').split('').slice(0, 4);
            
            digits.forEach((digit, i) => {
                if (inputs[i]) {
                    inputs[i].value = digit;
                    if (i < inputs.length - 1) {
                        inputs[i + 1].focus();
                    }
                }
            });
        });
    });
    
    // Фокус на первое поле
    inputs[0].focus();
}

// Таймер для повторной отправки
function startTimer() {
    let timeLeft = 60;
    const timerElement = document.getElementById('timer');
    const timerText = document.getElementById('timerText');
    
    const timer = setInterval(() => {
        timeLeft--;
        timerElement.textContent = timeLeft;
        
        if (timeLeft <= 0) {
            clearInterval(timer);
            timerText.innerHTML = `
                <button onclick="resendCode()" class="resend-button">
                    <i class="fas fa-redo"></i> Отправить код повторно
                </button>
            `;
        }
    }, 1000);
}

// Повторная отправка кода
async function resendCode() {
    const phone = localStorage.getItem('auth_phone');
    if (!phone) {
        document.getElementById('phoneStep').classList.remove('hidden');
        document.getElementById('codeStep').classList.add('hidden');
        return;
    }
    
    try {
        const response = await fetch('/api/auth/send-code.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ phone })
        });
        
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.error || 'Ошибка отправки кода');
        }
        
        document.getElementById('timerText').textContent = 'Запросить новый код через: ';
        startTimer();
        setupCodeInputs();
        
    } catch (error) {
        showError('Произошла ошибка при повторной отправке кода');
        console.error('Error:', error);
    }
}

// Возврат к вводу номера
document.getElementById('backButton').addEventListener('click', function() {
    document.getElementById('phoneStep').classList.remove('hidden');
    document.getElementById('codeStep').classList.add('hidden');
});

// Обработка формы кода
document.getElementById('codeForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const inputs = document.querySelectorAll('.code-inputs input');
    const code = Array.from(inputs).map(input => input.value).join('');
    const phone = localStorage.getItem('auth_phone');
    
    if (!phone) {
        showError('Номер телефона не найден. Пожалуйста, начните заново.');
        return;
    }
    
    if (code.length !== 4) {
        showError('Пожалуйста, введите код полностью');
        return;
    }
    
    try {
        const response = await fetch('/api/auth/verify-code.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ phone, code })
        });
        
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.error || 'Неверный код подтверждения');
        }
        
        // Сохраняем токен
        localStorage.setItem('auth_token', result.token);
        
        // Перенаправляем на главную
        window.location.href = 'index.html';
        
    } catch (error) {
        showError('Неверный код подтверждения. Пожалуйста, попробуйте еще раз.');
        console.error('Error:', error);
    }
});

// Показ ошибки
function showError(message) {
    const errorDiv = document.createElement('div');
    errorDiv.className = 'error-message';
    errorDiv.innerHTML = `
        <i class="fas fa-exclamation-circle"></i>
        ${message}
    `;
    
    // Удаляем предыдущие сообщения об ошибках
    document.querySelectorAll('.error-message').forEach(el => el.remove());
    
    // Добавляем новое сообщение
    const currentStep = document.querySelector('.auth-step:not(.hidden)');
    currentStep.querySelector('.auth-form').prepend(errorDiv);
    
    // Автоматически скрываем через 5 секунд
    setTimeout(() => {
        errorDiv.remove();
    }, 5000);
}

// Обработка формы Telegram
document.getElementById('telegramForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const telegramInput = document.getElementById('telegram');
    const username = telegramInput.value.replace('@', '');
    
    if (!username) {
        showError('Пожалуйста, введите ваш Telegram username');
        return;
    }
    
    try {
        const response = await fetch('/api/auth/send-code.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ telegram_username: username })
        });
        
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.error || 'Ошибка отправки кода');
        }
        
        // Сохраняем username для последующей проверки
        localStorage.setItem('auth_telegram', username);
        document.getElementById('confirmedTelegram').textContent = '@' + username;
        
        // Показываем форму ввода кода
        document.getElementById('telegramStep').classList.add('hidden');
        document.getElementById('codeStep').classList.remove('hidden');
        startTimer();
        setupCodeInputs();
        
    } catch (error) {
        showError('Произошла ошибка при отправке кода. Пожалуйста, попробуйте позже.');
        console.error('Error:', error);
    }
});

// Настройка полей ввода кода
function setupCodeInputs() {
    const inputs = document.querySelectorAll('.code-inputs input');
    
    inputs.forEach((input, index) => {
        // Очищаем значения
        input.value = '';
        
        // Автофокус на следующее поле
        input.addEventListener('input', function(e) {
            if (e.target.value.length === 1) {
                if (index < inputs.length - 1) {
                    inputs[index + 1].focus();
                } else {
                    // Если это последнее поле и все поля заполнены, отправляем форму
                    if (Array.from(inputs).every(input => input.value.length === 1)) {
                        document.getElementById('codeForm').dispatchEvent(new Event('submit'));
                    }
                }
            }
        });
        
        // Обработка удаления
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Backspace' && !e.target.value && index > 0) {
                inputs[index - 1].focus();
            }
        });
        
        // Только цифры
        input.addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/\D/g, '');
        });
        
        // Вставка кода целиком
        input.addEventListener('paste', function(e) {
            e.preventDefault();
            const pastedText = (e.clipboardData || window.clipboardData).getData('text');
            const digits = pastedText.replace(/\D/g, '').split('').slice(0, 4);
            
            digits.forEach((digit, i) => {
                if (inputs[i]) {
                    inputs[i].value = digit;
                    if (i < inputs.length - 1) {
                        inputs[i + 1].focus();
                    }
                }
            });
        });
    });
    
    // Фокус на первое поле
    inputs[0].focus();
}

// Таймер для повторной отправки
function startTimer() {
    let timeLeft = 60;
    const timerElement = document.getElementById('timer');
    const timerText = document.getElementById('timerText');
    
    const timer = setInterval(() => {
        timeLeft--;
        timerElement.textContent = timeLeft;
        
        if (timeLeft <= 0) {
            clearInterval(timer);
            timerText.innerHTML = `
                <button onclick="resendCode()" class="resend-button">
                    <i class="fas fa-redo"></i> Отправить код повторно
                </button>
            `;
        }
    }, 1000);
}

// Повторная отправка кода
async function resendCode() {
    const username = localStorage.getItem('auth_telegram');
    if (!username) {
        document.getElementById('telegramStep').classList.remove('hidden');
        document.getElementById('codeStep').classList.add('hidden');
        return;
    }
    
    try {
        const response = await fetch('/api/auth/send-code.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ telegram_username: username })
        });
        
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.error || 'Ошибка отправки кода');
        }
        
        document.getElementById('timerText').textContent = 'Запросить новый код через: ';
        startTimer();
        setupCodeInputs();
        
    } catch (error) {
        showError('Произошла ошибка при повторной отправке кода');
        console.error('Error:', error);
    }
}

// Возврат к вводу Telegram
document.getElementById('backButton').addEventListener('click', function() {
    document.getElementById('telegramStep').classList.remove('hidden');
    document.getElementById('codeStep').classList.add('hidden');
});

// Обработка формы кода
document.getElementById('codeForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const inputs = document.querySelectorAll('.code-inputs input');
    const code = Array.from(inputs).map(input => input.value).join('');
    const username = localStorage.getItem('auth_telegram');
    
    if (!username) {
        showError('Telegram username не найден. Пожалуйста, начните заново.');
        return;
    }
    
    if (code.length !== 4) {
        showError('Пожалуйста, введите код полностью');
        return;
    }
    
    try {
        const response = await fetch('/api/auth/verify-code.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ telegram_username: username, code })
        });
        
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.error || 'Неверный код подтверждения');
        }
        
        // Сохраняем токен
        localStorage.setItem('auth_token', result.token);
        
        // Перенаправляем на главную
        window.location.href = 'index.html';
        
    } catch (error) {
        showError('Неверный код подтверждения. Пожалуйста, попробуйте еще раз.');
        console.error('Error:', error);
    }
});

// Показ ошибки
function showError(message) {
    const errorDiv = document.createElement('div');
    errorDiv.className = 'error-message';
    errorDiv.innerHTML = `
        <i class="fas fa-exclamation-circle"></i>
        ${message}
    `;
    
    // Удаляем предыдущие сообщения об ошибках
    document.querySelectorAll('.error-message').forEach(el => el.remove());
    
    // Добавляем новое сообщение
    const currentStep = document.querySelector('.auth-step:not(.hidden)');
    currentStep.querySelector('.auth-form').prepend(errorDiv);
    
    // Автоматически скрываем через 5 секунд
    setTimeout(() => {
        errorDiv.remove();
    }, 5000);
} 