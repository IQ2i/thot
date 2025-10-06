import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        dismissAfter: { type: Number, default: 5000 },
    };

    connect() {
        setTimeout(() => {
            this.hide();
        }, this.dismissAfterValue);
    }

    hide() {
        setTimeout(() => {
            this.element.remove();
        }, 100);
    }
}
