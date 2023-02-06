interface s9eGlobal {
    TextFormatter: any
}

declare global {
    const s9e: s9eGlobal;
}

import Form from './src/forum/models/Form';
import Submission from './src/forum/models/Submission';
import Uploader from './src/forum/utils/Uploader';

declare module 'flarum/common/Model' {
    export default interface Model {
        formulaireForms(): Form[] | false

        formulaireSubmissions(): Submission[] | false
    }
}

declare module 'flarum/common/models/Forum' {
    export default interface Forum {
        formulaireSignUpForms(): Form[] | false
        formulaireComposerForms(): Form[] | false
    }
}

declare module 'flarum/forum/components/SignUpModal' {
    export default interface SignUpModal {
        formulaire: {
            [formId: string]: {
                value: any
                uploader: Uploader
            }
        }
        formulaireValidationErrors: any
    }
}

declare module 'flarum/forum/components/DiscussionComposer' {
    export default interface DiscussionComposer {
        formulaireValidationErrors: any
        formulaireRequestErrorAlert: any
    }
}
