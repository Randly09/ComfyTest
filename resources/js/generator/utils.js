export function sleep(ms) {
    return new Promise(
        resolve => setTimeout(resolve, ms)
    );
}

export function scrollToBottom() {
    window.scrollTo({
        top: document.body.scrollHeight,
        behavior: 'smooth',
    });
}