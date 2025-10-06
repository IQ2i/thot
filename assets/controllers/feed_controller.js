import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['messages'];

    observer = new MutationObserver(mutations => {
        mutations.forEach(mutation => {
            this.scrollToBottom();
        });
    });

    connect() {
        this.observer.observe(this.messagesTarget, {childList: true})
        this.scrollToBottom();
    }

    disconnect() {
        this.observer.disconnect();
    }

    scrollToBottom() {
        window.scrollTo({
            top: this.messagesTarget.scrollHeight,
        });
    }
}
