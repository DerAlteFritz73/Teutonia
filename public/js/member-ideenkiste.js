document.addEventListener('turbo:load', function () {
    document.querySelectorAll('.like-button').forEach(button => {
        button.addEventListener('click', function () {
            const suggestionId = this.dataset.suggestionId;
            const buttonElement = this;

            fetch(`/mitglieder/ideenkiste/${suggestionId}/like`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' }
            })
            .then(response => response.json())
            .then(data => {
                const icon = buttonElement.querySelector('i');
                if (data.liked) {
                    buttonElement.classList.replace('btn-outline-danger', 'btn-danger');
                    icon.classList.replace('bi-heart', 'bi-heart-fill');
                } else {
                    buttonElement.classList.replace('btn-danger', 'btn-outline-danger');
                    icon.classList.replace('bi-heart-fill', 'bi-heart');
                }
                buttonElement.querySelector('.like-count').textContent = data.likeCount;
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Fehler beim Liken. Bitte versuchen Sie es erneut.');
            });
        });
    });
});
