(()=>{var e={n:s=>{var r=s&&s.__esModule?()=>s.default:()=>s;return e.d(r,{a:r}),r},d:(s,r)=>{for(var i in r)e.o(r,i)&&!e.o(s,i)&&Object.defineProperty(s,i,{enumerable:!0,get:r[i]})},o:(e,s)=>Object.prototype.hasOwnProperty.call(e,s),r:e=>{"undefined"!=typeof Symbol&&Symbol.toStringTag&&Object.defineProperty(e,Symbol.toStringTag,{value:"Module"}),Object.defineProperty(e,"__esModule",{value:!0})}},s={};(()=>{"use strict";e.r(s);const r=flarum.core.compat["admin/app"];var i=e.n(r);i().initializers.add("fof-subscribed",(function(){i().extensionData.for("fof-subscribed").registerPermission({icon:"fas fa-bell",label:i().translator.trans("fof-subscribed.admin.permission.subscribe_to_discussion_created"),permission:"subscribeDiscussionCreated"},"start").registerPermission({icon:"fas fa-bell",label:i().translator.trans("fof-subscribed.admin.permission.subscribe_to_post_created"),permission:"subscribePostCreated"},"start").registerPermission({icon:"fas fa-bell",label:i().translator.trans("fof-subscribed.admin.permission.subscribe_to_user_created"),permission:"subscribeUserCreated"},"start").registerPermission({icon:"fas fa-gavel",label:i().translator.trans("fof-subscribed.admin.permission.subscribe_to_post_unapproved"),permission:"subscribePostUnapproved"},"moderate").registerPermission({icon:"fas fa-flag",label:i().translator.trans("fof-subscribed.admin.permission.subscribe_to_post_flagged"),permission:"subscribePostFlagged"},"moderate")}))})(),module.exports=s})();
//# sourceMappingURL=admin.js.map