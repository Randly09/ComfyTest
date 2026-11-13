function getRequiredElement(id) {
    const element =
        document.getElementById(id);

    if (!element) {
        throw new Error(
            `Required element #${id} was not found in the page.`
        );
    }

    return element;
}


export const elements = {

    form:
        getRequiredElement(
            'generatorForm'
        ),

    conversation:
        getRequiredElement(
            'conversation'
        ),

    welcome:
        getRequiredElement(
            'welcome'
        ),

    prompt:
        getRequiredElement(
            'prompt'
        ),

    negativePrompt:
        getRequiredElement(
            'negative_prompt'
        ),

    negativeContainer:
        getRequiredElement(
            'negativePromptContainer'
        ),

    workflow:
        getRequiredElement(
            'workflow'
        ),

    imageInput:
        getRequiredElement(
            'image'
        ),

    fileButton:
        getRequiredElement(
            'fileButton'
        ),

    filePreview:
        getRequiredElement(
            'filePreview'
        ),

    fileThumbnail:
        getRequiredElement(
            'fileThumbnail'
        ),

    fileName:
        getRequiredElement(
            'fileName'
        ),

    removeFile:
        getRequiredElement(
            'removeFile'
        ),

    dropOverlay:
        getRequiredElement(
            'dropOverlay'
        ),
};


export const statusBaseUrl =
    document.body.dataset.statusBaseUrl;


if (!statusBaseUrl) {
    throw new Error(
        'The body is missing data-status-base-url.'
    );
}