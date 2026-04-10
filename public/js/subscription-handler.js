/**
 * Newsletter Subscription Form Handler
 * Handles form submission with Cloudflare Turnstile verification
 */

function initSubscriptionForm(config) {
    const form = document.getElementById(config.formId);
    const button = document.getElementById(config.buttonId);
    const message = document.getElementById(config.messageId);

    if (!form || !button || !message) return;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const email = form.querySelector('input[name="email"]').value;
        const csrfToken = form.querySelector('input[name="_token"]').value;
        const turnstileResponse = form.querySelector('[name="cf-turnstile-response"]')?.value;

        // Check Turnstile verification
        if (!turnstileResponse) {
            showMessage(message, 'Please complete the security verification.', 'error', config.theme);
            return;
        }

        // Disable button and show loading state
        button.disabled = true;
        const originalText = button.textContent;
        button.textContent = config.loadingText || 'Subscribing...';
        message.classList.add('hidden');

        try {
            const response = await fetch(config.apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({ 
                    email,
                    'cf-turnstile-response': turnstileResponse
                }),
            });

            const data = await response.json();
            
            // Reset form and Turnstile after any response
            form.reset();
            if (typeof turnstile !== 'undefined') {
                turnstile.reset();
            }
            
            if (data.success) {
                showMessage(message, data.message, 'success', config.theme);
                
                // Reset button after delay
                setTimeout(() => {
                    button.textContent = originalText;
                    button.disabled = false;
                }, 2000);

                // Hide message after delay
                setTimeout(() => {
                    message.classList.add('hidden');
                }, 5000);
            } else {
                showMessage(message, data.message, 'warning', config.theme);
                button.textContent = originalText;
                button.disabled = false;

                setTimeout(() => {
                    message.classList.add('hidden');
                }, 5000);
            }

        } catch (error) {
            showMessage(message, 'An error occurred. Please try again.', 'error', config.theme);
            
            // Reset form and Turnstile on error
            form.reset();
            if (typeof turnstile !== 'undefined') {
                turnstile.reset();
            }
            
            button.textContent = originalText;
            button.disabled = false;

            setTimeout(() => {
                message.classList.add('hidden');
            }, 5000);
        }
    });
}

function showMessage(element, text, type, theme = 'dark') {
    element.classList.remove('hidden');
    element.textContent = text;

    // Remove all previous state classes
    const allClasses = [
        'text-green-200', 'bg-green-600/30', 'text-amber-200', 'bg-amber-600/30', 'text-red-200', 'bg-red-600/30',
        'text-green-100', 'bg-green-600/30', 'text-amber-100', 'bg-amber-600/30', 'text-red-100', 'bg-red-600/30',
        'bg-green-50', 'text-green-700', 'border-green-200', 'bg-amber-50', 'text-amber-700', 'border-amber-200',
        'bg-red-50', 'text-red-700', 'border-red-200', 'border'
    ];
    element.classList.remove(...allClasses);

    // Define color schemes
    const styles = {
        light: {
            success: ['border', 'bg-green-50', 'text-green-700', 'border-green-200'],
            warning: ['border', 'bg-amber-50', 'text-amber-700', 'border-amber-200'],
            error: ['border', 'bg-red-50', 'text-red-700', 'border-red-200']
        },
        dark: {
            success: ['text-green-200', 'bg-green-600/30'],
            warning: ['text-amber-200', 'bg-amber-600/30'],
            error: ['text-red-200', 'bg-red-600/30']
        }
    };

    // Apply styles
    const classes = styles[theme][type] || styles[theme].error;
    element.classList.add(...classes);
}
