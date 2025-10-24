import * as Turbo from '@hotwired/turbo';

const TurboHelper = class {
    constructor() {
        document.addEventListener('turbo:before-cache', () => {
            this.closeDialog();
            this.closeFlash();
            this.reenableSubmitButtons();
        });

        document.addEventListener('turbo:submit-start', (event) => {
            const submitter = event.detail.formSubmission.submitter;
            submitter.toggleAttribute('disabled', true);
            submitter.classList.add('turbo-submit-disabled');
        })

        document.addEventListener('turbo:before-fetch-request', (event) => {
            this.beforeFetchRequest(event);
        });

        document.addEventListener('turbo:before-fetch-response', (event) => {
            this.beforeFetchResponse(event);
        });

        this.initializeTransitions();
    }

    closeDialog() {
        document.querySelectorAll('el-dialog').forEach(dialog => dialog.hide());
    }

    closeFlash() {
        document.querySelectorAll('[data-controller="alert"]').forEach(flash => flash.remove());
    }

    isPreviewRendered() {
        return document.documentElement.hasAttribute('data-turbo-preview');
    }

    initializeTransitions() {
        document.addEventListener('turbo:visit', () => {
            // fade out the old body
            document.body.classList.add('turbo-loading');
        });

        document.addEventListener('turbo:before-render', (event) => {
            if (this.isPreviewRendered()) {
                // this is a preview that has been instantly swapped
                // remove .turbo-loading so the preview starts fully opaque
                event.detail.newBody.classList.remove('turbo-loading');
                // start fading out 1 frame later after opacity starts full
                requestAnimationFrame(() => {
                    document.body.classList.add('turbo-loading');
                });
            } else {
                const isRestoration = event.detail.newBody.classList.contains('turbo-loading');
                if (isRestoration) {
                    // this is a restoration (back button). Remove the class
                    // so it simply starts with full opacity

                    event.detail.newBody.classList.remove('turbo-loading');

                    return;
                }

                // when we are *about* to render a fresh page
                // we should already be faded out, so start us faded out
                event.detail.newBody.classList.add('turbo-loading');
            }
        });
        document.addEventListener('turbo:render', () => {
            if (!this.isPreviewRendered()) {
                // if this is a preview, then we do nothing: stay faded out
                // after rendering the REAL page, we first allow the .turbo-loading to
                // instantly start the page at lower opacity. THEN remove the class
                // one frame later, which allows the fade in
                requestAnimationFrame(() => {
                    document.body.classList.remove('turbo-loading');
                });
            }
        });
    }

    reenableSubmitButtons() {
        document.querySelectorAll('.turbo-submit-disabled').forEach((button) => {
            button.toggleAttribute('disabled', false);
            button.classList.remove('turbo-submit-disabled');
        });
    }

    beforeFetchRequest(event) {
        const frameId = event.detail.fetchOptions.headers['Turbo-Frame'];
        if (!frameId) {
            return;
        }

        const frame = document.querySelector(`#${frameId}`);

        if (!frame || !frame.dataset.turboFormRedirect) {
            return;
        }

        event.detail.fetchOptions.headers['Turbo-Frame-Redirect'] = 1;
    }

    beforeFetchResponse(event) {
        const fetchResponse = event.detail.fetchResponse;
        const redirectLocation = fetchResponse.response.headers.get('Turbo-Location');
        if (!redirectLocation) {
            return;
        }

        event.preventDefault();
        Turbo.clearCache();
        Turbo.visit(redirectLocation);
    }
}

export default new TurboHelper();