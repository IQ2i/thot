import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['deleteButton'];
    static values = {
        url: String,
    };

    connect() {
        // Update the delete button URL when the modal opens
        if (this.hasDeleteButtonTarget && this.hasUrlValue) {
            this.deleteButtonTarget.setAttribute('href', this.urlValue);
        }
    }

    // Method to be called before opening the modal to set the delete URL
    setDeleteUrl(event) {
        event.preventDefault();
        const deleteUrl = event.currentTarget.getAttribute('href') || event.currentTarget.dataset.deleteUrl;

        if (deleteUrl) {
            this.urlValue = deleteUrl;
            if (this.hasDeleteButtonTarget) {
                this.deleteButtonTarget.setAttribute('href', deleteUrl);
            }
        }
    }
}
