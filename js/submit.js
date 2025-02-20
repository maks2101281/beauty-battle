// Переключение между фото и видео
document.getElementById('mediaType').addEventListener('change', function(e) {
    const photoGroup = document.getElementById('photoGroup');
    const videoGroup = document.getElementById('videoGroup');
    const photoInput = document.getElementById('photo');
    const videoInput = document.getElementById('video');
    
    if (e.target.value === 'photo') {
        photoGroup.classList.remove('hidden');
        videoGroup.classList.add('hidden');
        photoInput.required = true;
        videoInput.required = false;
    } else {
        photoGroup.classList.add('hidden');
        videoGroup.classList.remove('hidden');
        photoInput.required = false;
        videoInput.required = true;
    }
});

// Предпросмотр фото
document.getElementById('photo').addEventListener('change', function(e) {
    const file = e.target.files[0];
    const preview = document.getElementById('photoPreview');
    
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.style.backgroundImage = `url(${e.target.result})`;
            preview.innerHTML = '';
        };
        reader.readAsDataURL(file);
    }
});

// Предпросмотр видео
document.getElementById('video').addEventListener('change', async function(e) {
    const file = e.target.files[0];
    const preview = document.getElementById('videoPreview');
    
    if (file) {
        // Проверка длительности видео
        const video = document.createElement('video');
        video.preload = 'metadata';
        
        video.onloadedmetadata = function() {
            window.URL.revokeObjectURL(video.src);
            if (video.duration > 15) {
                alert('Видео должно быть не длиннее 15 секунд');
                e.target.value = '';
                return;
            }
            
            // Создаем превью видео
            preview.innerHTML = `
                <video controls style="max-width: 100%; max-height: 200px;">
                    <source src="${URL.createObjectURL(file)}" type="${file.type}">
                </video>
            `;
        };
        
        video.src = URL.createObjectURL(file);
    }
});

// Отправка формы
document.getElementById('submitForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const name = document.getElementById('name').value;
    const mediaType = document.getElementById('mediaType').value;
    const file = mediaType === 'photo' ? 
        document.getElementById('photo').files[0] : 
        document.getElementById('video').files[0];
    
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const submissions = JSON.parse(localStorage.getItem('submissions') || '[]');
            submissions.push({
                id: Date.now(),
                name: name,
                mediaType: mediaType,
                media: e.target.result
            });
            
            localStorage.setItem('submissions', JSON.stringify(submissions));
            alert('Спасибо! Ваша заявка принята на рассмотрение.');
            document.getElementById('submitForm').reset();
            document.getElementById('photoPreview').innerHTML = '<i class="fas fa-image"></i><span>Предпросмотр фото</span>';
            document.getElementById('videoPreview').innerHTML = '<i class="fas fa-video"></i><span>Предпросмотр видео</span>';
        };
        
        reader.readAsDataURL(file);
    }
}); 