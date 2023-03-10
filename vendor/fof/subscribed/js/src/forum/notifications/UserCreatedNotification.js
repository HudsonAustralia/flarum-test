import app from 'flarum/forum/app';
import Notification from 'flarum/forum/components/Notification';

export default class UserCreatedNotification extends Notification {
  icon() {
    return 'fas fa-user-plus';
  }

  href() {
    const notification = this.attrs.notification;

    return app.route.user(notification.subject());
  }

  content() {
    return app.translator.trans('fof-subscribed.forum.notifications.user_created_text', { user: this.attrs.notification.fromUser() });
  }

  excerpt() {
    return null;
  }
}
