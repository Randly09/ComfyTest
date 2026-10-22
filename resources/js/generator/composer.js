import { elements } from './dom.js';

let composerPreviewUrl = null;
let dragDepth = 0;

export function resizePrompt() {
    elements.prompt.style.height = 'auto';
    elements.prompt.style.height =
        Math.min(elements.prompt.scrollHeight, 180) + 'px';
}

export function clearAttachment() {
    elements.imageInput.value = '';
    elements.filePreview.style.display = 'none';
    elements.fileThumbnail.src = '';
    elements.fileName.textContent = '';

    if (composerPreviewUrl) {
        URL.revokeObjectURL(composerPreviewUrl);
        composerPreviewUrl = null;
    }
}

function updateWorkflowFields() {
    if (elements.workflow.value === 'auto') {
        elements.negativeContainer.style.display = 'none';
        elements.fileButton.style.display = 'flex';
        elements.imageInput.required = false;
        return;
    }

    if (elements.workflow.value === 'image_to_image') {
        elements.negativeContainer.style.display = 'block';
        elements.fileButton.style.display = 'flex';
        elements.imageInput.required = true;
        return;
    }

    elements.negativeContainer.style.display = 'block';
    elements.fileButton.style.display = 'none';
    elements.imageInput.required = false;
    clearAttachment();
}

function attachFile(file) {
    if (!file) {
        return;
    }

    const acceptedTypes = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    if (!acceptedTypes.includes(file.type)) {
        alert('Please upload a JPG, PNG or WEBP image.');
        return;
    }

    if (file.size > 10 * 1024 * 1024) {
        alert('The image must be smaller than 10 MB.');
        return;
    }

    const transfer = new DataTransfer();
    transfer.items.add(file);
    elements.imageInput.files = transfer.files;

    if (composerPreviewUrl) {
        URL.revokeObjectURL(composerPreviewUrl);
    }

    composerPreviewUrl = URL.createObjectURL(file);
    elements.fileThumbnail.src = composerPreviewUrl;
    elements.fileName.textContent = file.name;
    elements.filePreview.style.display = 'block';

    if (elements.workflow.value === 'text_to_image') {
        elements.workflow.value = 'auto';
        updateWorkflowFields();
    }
}

function setupDragAndDrop() {
    document.addEventListener('dragenter', event => {
        event.preventDefault();

        if (!event.dataTransfer.types.includes('Files')) {
            return;
        }

        dragDepth++;
        elements.dropOverlay.classList.add('active');
    });

    document.addEventListener('dragover', event => {
        event.preventDefault();
    });

    document.addEventListener('dragleave', event => {
        event.preventDefault();
        dragDepth--;

        if (dragDepth <= 0) {
            dragDepth = 0;
            elements.dropOverlay.classList.remove('active');
        }
    });

    document.addEventListener('drop', event => {
        event.preventDefault();
        dragDepth = 0;
        elements.dropOverlay.classList.remove('active');

        if (event.dataTransfer.files.length > 0) {
            attachFile(event.dataTransfer.files[0]);
        }
    });
}

function setupEnterToSend() {
    elements.prompt.addEventListener('keydown', event => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            elements.form.requestSubmit();
        }
    });
}

export function initializeComposer() {
    elements.prompt.addEventListener('input', resizePrompt);
    elements.workflow.addEventListener('change', updateWorkflowFields);

    elements.fileButton.addEventListener('click', () => {
        elements.imageInput.click();
    });

    elements.imageInput.addEventListener('change', () => {
        if (elements.imageInput.files.length > 0) {
            attachFile(elements.imageInput.files[0]);
        }
    });

    elements.removeFile.addEventListener('click', clearAttachment);

    setupDragAndDrop();
    setupEnterToSend();
    updateWorkflowFields();
    resizePrompt();
}