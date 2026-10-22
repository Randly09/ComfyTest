export const elements = {
    form: document.getElementById('generatorForm'),
    conversation: document.getElementById('conversation'),
    welcome: document.getElementById('welcome'),
    prompt: document.getElementById('prompt'),
    negativePrompt: document.getElementById('negative_prompt'),
    negativeContainer: document.getElementById('negativePromptContainer'),
    workflow: document.getElementById('workflow'),
    imageInput: document.getElementById('image'),
    fileButton: document.getElementById('fileButton'),
    filePreview: document.getElementById('filePreview'),
    fileThumbnail: document.getElementById('fileThumbnail'),
    fileName: document.getElementById('fileName'),
    removeFile: document.getElementById('removeFile'),
    dropOverlay: document.getElementById('dropOverlay'),
};

export const statusBaseUrl =
    document.body.dataset.statusBaseUrl;