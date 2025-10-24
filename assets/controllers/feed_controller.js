import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['messages'];

    observer = new MutationObserver(mutations => {
        mutations.forEach(mutation => {
            this.scrollToBottom();
        });
    });

    connect() {
        this.observer.observe(this.messagesTarget, {childList: true, subtree: true})
        this.scrollToBottom();
    }

    disconnect() {
        this.observer.disconnect();
    }

    scrollToBottom() {
        const lastMessage = this.messagesTarget.lastElementChild;
        if (lastMessage) {
            lastMessage.scrollIntoView({ block: 'start' });
        }
    }
}
