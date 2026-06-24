/* iH4x OS - shared utilities (vanilla, no bundler) */
(function () {
    'use strict';

    const root = document.documentElement;
    const THEME_KEY = 'ih4x.theme';
    const LANG_KEY  = 'ih4x.lang';

    /* =====================================================================
       i18n
       ===================================================================== */

    const STRINGS = {
        en: {
            // Navigation
            'nav.library':       'Library',
            'nav.all':           'All Notes',
            'nav.pinned':        'Pinned',
            'nav.favorites':     'Favorites',
            'nav.archive':       'Archive',
            'nav.trash':         'Trash',
            'nav.tags':          'Tags',
            'nav.no_tags':       'No tags yet',
            'nav.smart_views':   'Smart Views',
            'nav.folders':       'Folders',
            'nav.no_folders':    'No folders yet',
            'nav.untitled_folder':'Untitled Folder',
            'nav.root':          'Workspace',
            'nav.collapse':      'Collapse folder',
            'nav.expand':        'Expand folder',

            // Actions / topbar
            'action.new':              'New Note',
            'action.search_placeholder':'Search your thoughts…',
            'action.search_aria':      'Search notes',
            'action.menu_aria':        'Open menu',
            'action.toggle_theme':     'Toggle theme',
            'action.command_palette':  'Command palette',
            'action.settings':         'Settings',
            'action.toggle_list':      'Toggle list view',
            'action.toggle_lang':      'Toggle language',
            'action.toggle_sidebar':   'Toggle sidebar',
            'action.add_folder':       'Add folder',
            'action.add_subfolder':    'Add subfolder',
            'action.rename':           'Rename',
            'action.recolor':          'Change color',
            'action.change_icon':      'Change icon',
            'action.delete_folder':    'Delete folder',
            'action.move_to_folder':   'Move to folder',
            'action.create_here':      'New note here',

            // Sort
            'sort.updated': 'Updated',
            'sort.created': 'Created',
            'sort.title':   'Title A→Z',
            'sort.length':  'Length',
            'sort.aria':    'Sort by',

            // Bulk
            'bulk.selected':  '{n} selected',
            'bulk.favorite':  'Favorite',
            'bulk.pin':       'Pin',
            'bulk.archive':   'Archive',
            'bulk.restore':   'Restore',
            'bulk.trash':     'Trash',
            'bulk.delete':    'Delete',
            'bulk.aria':      'Bulk actions',
            'bulk.move':      'Move',

            // Cards
            'card.untitled':  'Untitled',
            'card.empty':     'Empty note. Click to start writing…',

            // Empty / state
            'empty.no_match':       'No matches',
            'empty.no_match_q':     'No notes match "{q}".',
            'empty.trash_title':    'Trash is empty',
            'empty.trash_desc':     'Deleted notes appear here.',
            'empty.title':          'Nothing here yet',
            'empty.desc':           'Capture a thought, draft a doc, or start a checklist.',
            'empty.cta':            'Create your first note',
            'empty.error':          'Error connecting to iH4x OS Core.',

            // Editor toolbar
            'toolbar.back':              'Back',
            'toolbar.title_placeholder': 'Untitled',
            'toolbar.title_aria':        'Note title',
            'toolbar.pin':               'Pin',
            'toolbar.fav':               'Favorite',
            'toolbar.color':             'Color',
            'toolbar.tags':              'Tags',
            'toolbar.outline':           'Outline',
            'toolbar.focus':             'Focus mode',
            'toolbar.more':              'More',
            'toolbar.editor_aria':       'Note editor',
            'toolbar.outline_title':     'On this page',
            'toolbar.outline_empty':     'No headings yet',

            // Status
            'status.ready':         'Ready',
            'status.loading':       'Loading…',
            'status.saving':        'Saving…',
            'status.saved':         'Saved',
            'status.saved_rel':     'Saved · {time}',
            'status.typing':        'Typing…',
            'status.error_load':    'Failed to load',
            'status.error_save':    'Save failed',
            'status.uploading':     'Uploading…',
            'status.words_chars':   '{w} words · {c} chars',
            'status.just_now':      'just now',

            // Slash menu
            'slash.placeholder': 'Filter blocks…',
            'slash.aria':        'Block menu',
            'slash.h1':          'Heading 1',
            'slash.h2':          'Heading 2',
            'slash.h3':          'Heading 3',
            'slash.text':        'Text',
            'slash.ul':          'Bulleted list',
            'slash.ol':          'Numbered list',
            'slash.todo':        'To-do',
            'slash.quote':       'Quote',
            'slash.code':        'Code block',
            'slash.divider':     'Divider',
            'slash.image':       'Image',
            'slash.table':       'Table',
            'slash.callout':     'Callout',

            // Color & tags popovers
            'color.title':   'Card color',
            'tag.title':     'Tags',
            'tag.placeholder':'Add tag, press Enter',

            // More menu
            'more.export_md':   'Export Markdown',
            'more.export_html': 'Export HTML',
            'more.export_txt':  'Export Plain text',
            'more.duplicate':   'Duplicate',
            'more.versions':    'Version history',
            'more.shortcuts':   'Keyboard shortcuts',
            'more.trash':       'Move to trash',

            // Versions drawer
            'versions.title':        'Version history',
            'versions.empty':        'No previous versions yet.',
            'versions.close':        'Close',
            'versions.restore':      'Restore',
            'versions.confirm_title':'Restore this version?',
            'versions.confirm_msg':  'Current content will be saved as a new version before restoring.',
            'versions.restored':     'Version restored.',

            // Confirms
            'confirm.trash_title':       'Move to trash?',
            'confirm.trash_msg':         '"{title}" will be moved to trash.',
            'confirm.trash_ok':          'Move to trash',
            'confirm.delete_title':      'Delete forever?',
            'confirm.delete_msg':        'This cannot be undone.',
            'confirm.delete_ok':         'Delete forever',
            'confirm.empty_trash_title': 'Empty trash ({n})?',
            'confirm.empty_trash_msg':   'All trashed notes will be permanently deleted.',
            'confirm.empty_trash_ok':    'Empty trash',
            'confirm.bulk_trash_title':  'Move {n} notes to trash?',
            'confirm.bulk_delete_title': 'Delete {n} notes forever?',

            // Toasts
            'toast.moved_trash':        'Moved to trash.',
            'toast.undo':               'Undo',
            'toast.duplicated':         'Duplicated.',
            'toast.archived':           'Note archived.',
            'toast.unarchived':         'Note unarchived.',
            'toast.applied':            'Applied: {op}',
            'toast.trash_emptied':      'Trash emptied.',
            'toast.trash_already_empty':'Trash is already empty.',
            'toast.open_trash_first':   'Open Trash first.',
            'toast.save_first':         'Save the note first.',
            'toast.cant_edit_trash':    'Restore the note to edit it.',
            'toast.retry':              'Retry',
            'toast.save_failed':        'Could not save: {msg}',
            'toast.dismiss':            'Dismiss',

            // Palette
            'palette.placeholder':      'Type a command or search notes…',
            'palette.aria':             'Command palette',
            'palette.new_note':         'New note',
            'palette.new_note_hint':    'Create a fresh note',
            'palette.toggle_theme':     'Toggle theme',
            'palette.toggle_theme_hint':'Light / Dark',
            'palette.toggle_list':      'Toggle list view',
            'palette.toggle_list_hint': 'Grid / List',
            'palette.toggle_lang':      'Toggle language',
            'palette.open_settings':    'Open settings',
            'palette.show_all':         'Show All Notes',
            'palette.show_pinned':      'Show Pinned',
            'palette.show_favorites':   'Show Favorites',
            'palette.show_archive':     'Show Archive',
            'palette.show_trash':       'Show Trash',
            'palette.empty_trash':      'Empty Trash',
            'palette.open_note':        'Open: {title}',
            'palette.tag':              'Tag: #{name}',
            'palette.hint_navigate':    'navigate',
            'palette.hint_select':      'select',
            'palette.hint_close':       'close',

            // Settings modal
            'settings.title':       'Settings',
            'settings.appearance':  'Appearance',
            'settings.language':    'Language',
            'settings.lang_en':     'English',
            'settings.lang_ar':     'العربية',
            'settings.theme':       'Theme',
            'settings.theme_dark':  'Dark',
            'settings.theme_light': 'Light',
            'settings.shortcuts':   'Keyboard shortcuts',
            'settings.close':       'Close',

            // Shortcut help
            'shortcuts.title':            'Keyboard shortcuts',
            'shortcuts.got_it':           'Got it',
            'shortcuts.or':               'or',
            'shortcuts.section_general':  'General',
            'shortcuts.section_format':   'Formatting',
            'shortcuts.section_view':     'View',
            'shortcuts.section_markdown': 'Markdown',
            'shortcuts.save':             'Save now',
            'shortcuts.palette':          'Command palette',
            'shortcuts.bold':             'Bold',
            'shortcuts.italic':           'Italic',
            'shortcuts.underline':        'Underline',
            'shortcuts.link':             'Insert link',
            'shortcuts.headings':         'Headings 1 / 2 / 3',
            'shortcuts.lists':            'Numbered / Bulleted / To-do',
            'shortcuts.outline':          'Toggle outline',
            'shortcuts.focus':            'Focus mode',
            'shortcuts.slash':            'Open block menu',

            // Phase 2
            'auth.sign_in':         'Sign in',
            'auth.username':        'Username',
            'auth.password':        'Password',
            'auth.remember':        'Remember me',
            'auth.forgot':          'Forgot password?',
            'auth.invalid':         'Invalid username or password.',
            'auth.session_expired': 'Session expired. Please sign in again.',
            'auth.reset_password':  'Reset password',
            'auth.logout':          'Logout',
            'auth.create_account':   'Create account',
            'auth.err.invite_invalid': 'Invite link expired or invalid.',
            'auth.err.fill_fields': 'Fill required fields. Password must be at least 6 characters.',
            'auth.err.username_exists': 'Username already exists.',
            'auth.err.password_short': 'Password must be at least 6 characters.',
            'auth.err.reset_invalid': 'Reset link expired or invalid.',
            'auth.err.invalid_credentials': 'Invalid username or password.',
            'auth.success.password_updated': 'Password updated. Sign in with the new password.',
            'setup.title': 'iH4x OS Setup',
            'setup.workspace': 'Workspace',
            'setup.workspace_name': 'Workspace name',
            'setup.owner_account': 'Owner account',
            'setup.deployment_mode': 'Deployment mode',
            'setup.single_lan': 'Single LAN server',
            'setup.nextcloud': 'NextCloud synced copies',
            'setup.finish': 'Finish setup',
            'setup.err.fill_fields': 'Fill all fields. Password must be at least 6 characters.',
            'admin.users':          'Users',
            'admin.roles':          'Roles',
            'admin.settings':       'System Settings',
            'admin.invite':         'Invite User',
            'admin.deactivate':     'Deactivate',
            'admin.reset_link':     'Generate reset link',
            'admin.role_cloned':    'Role cloned.',
            'admin.saved':          'Saved.',
            'hq.active_users':      'Active Users',
            'hq.recent_activity':   'Recent Activity',
            'hq.storage':           'Storage',
            'hq.go_admin':          'Admin Panel',
            'profile.title':        'Profile',
            'profile.display_name': 'Display name',
            'profile.email':        'Email',
            'profile.password_hint':'Leave blank to keep current password',
            'share.title':         'Share',
            'share.with_people':   'Share with people',
            'share.link':          'Share link',
            'share.can_view':      'Can view',
            'share.can_edit':      'Can edit',
            'share.remove':        'Remove',
            'share.copy_link':     'Copy link',
            'share.link_copied':   'Link copied.',
            'share.enable_link':   'Enable link',
            'share.expires':       'Expires',
            'share.never':         'Never',
            'share.shared_with':   'Shared with {n}',
            'nav.mentions':        'Mentions',
            'nav.shared_with_me':  'Shared with me',
            'presence.typing':     '{name} is editing',
            'presence.n_editing':  '{n} people editing',

            // Editor extras
            'editor.remote_updated': 'Updated remotely',
            'editor.reload':         'Reload',
            'editor.linked_from':    'Linked from',
            'editor.highlight':      'Highlight an idea\u2026',
            'editor.format':         'Format',
            'format.bold':           'Bold',
            'format.italic':         'Italic',
            'format.underline':      'Underline',
            'format.strikethrough':  'Strikethrough',
            'format.inline_code':    'Inline code',
            'format.link':           'Link',
            'profile.save':          'Save',

            // Common
            'common.confirm': 'Confirm',
            'common.cancel':  'Cancel',
            'common.ok':      'OK',
            'common.rename':  'Rename',
            'common.delete':  'Delete',
            'common.move':    'Move',
            'common.none':    'None',
            'common.folder_name': 'Folder name',
            'common.folder_icon': 'Folder icon or emoji',
            'common.folder_color':'Folder color',
            'common.move_to': 'Move to',
        },
        ar: {
            // Navigation
            'nav.library':       'المكتبة',
            'nav.all':           'كل الملاحظات',
            'nav.pinned':        'المثبتة',
            'nav.favorites':     'المفضلة',
            'nav.archive':       'الأرشيف',
            'nav.trash':         'المهملات',
            'nav.tags':          'الوسوم',
            'nav.no_tags':       'لا توجد وسوم بعد',
            'nav.smart_views':   'التطبيق',
            'nav.folders':       'المجلدات',
            'nav.no_folders':    'لا توجد مجلدات بعد',
            'nav.untitled_folder':'مجلد بلا اسم',
            'nav.root':          'مساحة العمل',
            'nav.collapse':      'طي المجلد',
            'nav.expand':        'فتح المجلد',

            // Actions / topbar
            'action.new':              'ملاحظة جديدة',
            'action.search_placeholder':'ابحث في أفكارك…',
            'action.search_aria':      'البحث في الملاحظات',
            'action.menu_aria':        'فتح القائمة',
            'action.toggle_theme':     'تبديل المظهر',
            'action.command_palette':  'لوحة الأوامر',
            'action.settings':         'الإعدادات',
            'action.toggle_list':      'تبديل العرض',
            'action.toggle_lang':      'تبديل اللغة',
            'action.toggle_sidebar':   'إظهار/إخفاء الشريط الجانبي',
            'action.add_folder':       'إضافة مجلد',
            'action.add_subfolder':    'إضافة مجلد فرعي',
            'action.rename':           'إعادة تسمية',
            'action.recolor':          'تغيير اللون',
            'action.change_icon':      'تغيير الأيقونة',
            'action.delete_folder':    'حذف المجلد',
            'action.move_to_folder':   'نقل إلى مجلد',
            'action.create_here':      'ملاحظة جديدة هنا',

            // Sort
            'sort.updated': 'آخر تحديث',
            'sort.created': 'تاريخ الإنشاء',
            'sort.title':   'العنوان أ→ي',
            'sort.length':  'الطول',
            'sort.aria':    'ترتيب حسب',

            // Bulk
            'bulk.selected':  '{n} محددة',
            'bulk.favorite':  'مفضل',
            'bulk.pin':       'تثبيت',
            'bulk.archive':   'أرشفة',
            'bulk.restore':   'استعادة',
            'bulk.trash':     'حذف',
            'bulk.delete':    'حذف نهائي',
            'bulk.aria':      'إجراءات جماعية',
            'bulk.move':      'نقل',

            // Cards
            'card.untitled':  'بدون عنوان',
            'card.empty':     'ملاحظة فارغة. اضغط لبدء الكتابة…',

            // Empty / state
            'empty.no_match':       'لا توجد نتائج',
            'empty.no_match_q':     'لا توجد ملاحظات تطابق "{q}".',
            'empty.trash_title':    'المهملات فارغة',
            'empty.trash_desc':     'الملاحظات المحذوفة تظهر هنا.',
            'empty.title':          'لا يوجد شيء هنا بعد',
            'empty.desc':           'دوّن فكرة أو ابدأ مستنداً أو قائمة مهام.',
            'empty.cta':            'أنشئ أول ملاحظة',
            'empty.error':          'تعذر الاتصال بنواة iH4x OS.',

            // Editor toolbar
            'toolbar.back':              'رجوع',
            'toolbar.title_placeholder': 'بدون عنوان',
            'toolbar.title_aria':        'عنوان الملاحظة',
            'toolbar.pin':               'تثبيت',
            'toolbar.fav':               'مفضل',
            'toolbar.color':             'اللون',
            'toolbar.tags':              'الوسوم',
            'toolbar.outline':           'الفهرس',
            'toolbar.focus':             'وضع التركيز',
            'toolbar.more':              'المزيد',
            'toolbar.editor_aria':       'محرر الملاحظة',
            'toolbar.outline_title':     'في هذه الصفحة',
            'toolbar.outline_empty':     'لا توجد عناوين بعد',

            // Status
            'status.ready':         'جاهز',
            'status.loading':       'جارٍ التحميل…',
            'status.saving':        'جارٍ الحفظ…',
            'status.saved':         'محفوظ',
            'status.saved_rel':     'محفوظ · {time}',
            'status.typing':        'كتابة…',
            'status.error_load':    'فشل التحميل',
            'status.error_save':    'فشل الحفظ',
            'status.uploading':     'جارٍ الرفع…',
            'status.words_chars':   '{w} كلمة · {c} حرف',
            'status.just_now':      'الآن',

            // Slash menu
            'slash.placeholder': 'تصفية الكتل…',
            'slash.aria':        'قائمة الكتل',
            'slash.h1':          'عنوان 1',
            'slash.h2':          'عنوان 2',
            'slash.h3':          'عنوان 3',
            'slash.text':        'نص',
            'slash.ul':          'قائمة نقطية',
            'slash.ol':          'قائمة مرقمة',
            'slash.todo':        'مهمة',
            'slash.quote':       'اقتباس',
            'slash.code':        'كتلة كود',
            'slash.divider':     'فاصل',
            'slash.image':       'صورة',
            'slash.table':       'جدول',
            'slash.callout':     'تنبيه',

            // Color & tags popovers
            'color.title':   'لون البطاقة',
            'tag.title':     'الوسوم',
            'tag.placeholder':'أضف وسماً ثم اضغط Enter',

            // More menu
            'more.export_md':   'تصدير Markdown',
            'more.export_html': 'تصدير HTML',
            'more.export_txt':  'تصدير نص عادي',
            'more.duplicate':   'تكرار',
            'more.versions':    'محفوظات النسخ',
            'more.shortcuts':   'اختصارات لوحة المفاتيح',
            'more.trash':       'نقل إلى المهملات',

            // Versions drawer
            'versions.title':        'محفوظات النسخ',
            'versions.empty':        'لا توجد نسخ سابقة بعد.',
            'versions.close':        'إغلاق',
            'versions.restore':      'استعادة',
            'versions.confirm_title':'استعادة هذه النسخة؟',
            'versions.confirm_msg':  'سيُحفظ المحتوى الحالي كنسخة جديدة قبل الاستعادة.',
            'versions.restored':     'تمت استعادة النسخة.',

            // Confirms
            'confirm.trash_title':       'النقل إلى المهملات؟',
            'confirm.trash_msg':         'سيتم نقل "{title}" إلى المهملات.',
            'confirm.trash_ok':          'نقل إلى المهملات',
            'confirm.delete_title':      'حذف نهائي؟',
            'confirm.delete_msg':        'لا يمكن التراجع عن هذا الإجراء.',
            'confirm.delete_ok':         'حذف نهائي',
            'confirm.empty_trash_title': 'تفريغ المهملات ({n})؟',
            'confirm.empty_trash_msg':   'سيتم حذف جميع الملاحظات في المهملات نهائياً.',
            'confirm.empty_trash_ok':    'تفريغ المهملات',
            'confirm.bulk_trash_title':  'نقل {n} ملاحظة إلى المهملات؟',
            'confirm.bulk_delete_title': 'حذف {n} ملاحظة نهائياً؟',

            // Toasts
            'toast.moved_trash':        'تم النقل إلى المهملات.',
            'toast.undo':               'تراجع',
            'toast.duplicated':         'تم التكرار.',
            'toast.archived':           'تمت أرشفة الملاحظة.',
            'toast.unarchived':         'تمت إعادة الملاحظة من الأرشيف.',
            'toast.applied':            'تم التنفيذ: {op}',
            'toast.trash_emptied':      'تم تفريغ المهملات.',
            'toast.trash_already_empty':'المهملات فارغة بالفعل.',
            'toast.open_trash_first':   'افتح المهملات أولاً.',
            'toast.save_first':         'احفظ الملاحظة أولاً.',
            'toast.cant_edit_trash':    'استعد الملاحظة لتعديلها.',
            'toast.retry':              'إعادة المحاولة',
            'toast.save_failed':        'تعذر الحفظ: {msg}',
            'toast.dismiss':            'إغلاق',

            // Palette
            'palette.placeholder':      'اكتب أمراً أو ابحث في الملاحظات…',
            'palette.aria':             'لوحة الأوامر',
            'palette.new_note':         'ملاحظة جديدة',
            'palette.new_note_hint':    'إنشاء ملاحظة جديدة',
            'palette.toggle_theme':     'تبديل المظهر',
            'palette.toggle_theme_hint':'فاتح / داكن',
            'palette.toggle_list':      'تبديل العرض',
            'palette.toggle_list_hint': 'شبكة / قائمة',
            'palette.toggle_lang':      'تبديل اللغة',
            'palette.open_settings':    'فتح الإعدادات',
            'palette.show_all':         'عرض كل الملاحظات',
            'palette.show_pinned':      'عرض المثبتة',
            'palette.show_favorites':   'عرض المفضلة',
            'palette.show_archive':     'عرض الأرشيف',
            'palette.show_trash':       'عرض المهملات',
            'palette.empty_trash':      'تفريغ المهملات',
            'palette.open_note':        'فتح: {title}',
            'palette.tag':              'وسم: #{name}',
            'palette.hint_navigate':    'تنقل',
            'palette.hint_select':      'اختيار',
            'palette.hint_close':       'إغلاق',

            // Settings modal
            'settings.title':       'الإعدادات',
            'settings.appearance':  'المظهر',
            'settings.language':    'اللغة',
            'settings.lang_en':     'English',
            'settings.lang_ar':     'العربية',
            'settings.theme':       'السمة',
            'settings.theme_dark':  'داكن',
            'settings.theme_light': 'فاتح',
            'settings.shortcuts':   'اختصارات لوحة المفاتيح',
            'settings.close':       'إغلاق',

            // Shortcut help
            'shortcuts.title':            'اختصارات لوحة المفاتيح',
            'shortcuts.got_it':           'حسناً',
            'shortcuts.or':               'أو',
            'shortcuts.section_general':  'عام',
            'shortcuts.section_format':   'التنسيق',
            'shortcuts.section_view':     'العرض',
            'shortcuts.section_markdown': 'Markdown',
            'shortcuts.save':             'حفظ الآن',
            'shortcuts.palette':          'لوحة الأوامر',
            'shortcuts.bold':             'عريض',
            'shortcuts.italic':           'مائل',
            'shortcuts.underline':        'تسطير',
            'shortcuts.link':             'إدراج رابط',
            'shortcuts.headings':         'عناوين 1 / 2 / 3',
            'shortcuts.lists':            'مرقمة / نقطية / مهام',
            'shortcuts.outline':          'إظهار/إخفاء الفهرس',
            'shortcuts.focus':            'وضع التركيز',
            'shortcuts.slash':            'فتح قائمة الكتل',

            // Phase 2
            'auth.sign_in':         'تسجيل الدخول',
            'auth.username':        'اسم المستخدم',
            'auth.password':        'كلمة المرور',
            'auth.remember':        'تذكرني',
            'auth.forgot':          'نسيت كلمة المرور؟',
            'auth.invalid':         'اسم المستخدم أو كلمة المرور غير صحيحة.',
            'auth.session_expired': 'انتهت الجلسة. يرجى تسجيل الدخول مرة أخرى.',
            'auth.reset_password':  'إعادة تعيين كلمة المرور',
            'auth.logout':          'تسجيل الخروج',
            'auth.create_account':   'إنشاء حساب',
            'auth.err.invite_invalid': 'رابط الدعوة منتهي الصلاحية أو غير صالح.',
            'auth.err.fill_fields': 'يرجى ملء الحقول المطلوبة. يجب أن تكون كلمة المرور 6 أحرف على الأقل.',
            'auth.err.username_exists': 'اسم المستخدم موجود بالفعل.',
            'auth.err.password_short': 'يجب أن تكون كلمة المرور 6 أحرف على الأقل.',
            'auth.err.reset_invalid': 'رابط إعادة التعيين منتهي الصلاحية أو غير صالح.',
            'auth.err.invalid_credentials': 'اسم المستخدم أو كلمة المرور غير صحيحة.',
            'auth.success.password_updated': 'تم تحديث كلمة المرور. سجل الدخول باستخدام كلمة المرور الجديدة.',
            'setup.title': 'إعداد iH4x OS',
            'setup.workspace': 'مساحة العمل',
            'setup.workspace_name': 'اسم مساحة العمل',
            'setup.owner_account': 'حساب المالك',
            'setup.deployment_mode': 'وضع النشر',
            'setup.single_lan': 'خادم محلي فردي LAN',
            'setup.nextcloud': 'نسخ متزامنة مع NextCloud',
            'setup.finish': 'إنهاء الإعداد',
            'setup.err.fill_fields': 'يرجى ملء جميع الحقول. يجب أن تكون كلمة المرور 6 أحرف على الأقل.',
            'admin.users':          'المستخدمون',
            'admin.roles':          'الأدوار',
            'admin.settings':       'إعدادات النظام',
            'admin.invite':         'دعوة مستخدم',
            'admin.deactivate':     'تعطيل',
            'admin.reset_link':     'إنشاء رابط إعادة تعيين',
            'admin.role_cloned':    'تم نسخ الدور.',
            'admin.saved':          'تم الحفظ.',
            'hq.active_users':      'المستخدمون النشطون',
            'hq.recent_activity':   'النشاط الأخير',
            'hq.storage':           'التخزين',
            'hq.go_admin':          'لوحة الإدارة',
            'profile.title':        'الملف الشخصي',
            'profile.display_name': 'الاسم المعروض',
            'profile.email':        'البريد الإلكتروني',
            'profile.password_hint':'اتركه فارغاً للإبقاء على كلمة المرور الحالية',
            'share.title':         'مشاركة',
            'share.with_people':   'مشاركة مع أشخاص',
            'share.link':          'رابط المشاركة',
            'share.can_view':      'يمكنه العرض',
            'share.can_edit':      'يمكنه التعديل',
            'share.remove':        'إزالة',
            'share.copy_link':     'نسخ الرابط',
            'share.link_copied':   'تم نسخ الرابط.',
            'share.enable_link':   'تفعيل الرابط',
            'share.expires':       'ينتهي',
            'share.never':         'أبداً',
            'share.shared_with':   'مشاركة مع {n}',
            'nav.mentions':        'الإشارات',
            'nav.shared_with_me':  'مشارك معي',
            'presence.typing':     '{name} يعدّل الآن',
            'presence.n_editing':  '{n} أشخاص يعدّلون الآن',

            // Editor extras
            'editor.remote_updated': 'تم التحديث عن بُعد',
            'editor.reload':         'إعادة تحميل',
            'editor.linked_from':    'مرتبط من',
            'editor.highlight':      'أبرز فكرة\u2026',
            'editor.format':         'تنسيق',
            'format.bold':           'عريض',
            'format.italic':         'مائل',
            'format.underline':      'تسطير',
            'format.strikethrough':  'يتوسطه خط',
            'format.inline_code':    'كود',
            'format.link':           'رابط',
            'profile.save':          'حفظ',

            // Common
            'common.confirm': 'تأكيد',
            'common.cancel':  'إلغاء',
            'common.ok':      'موافق',
            'common.rename':  'إعادة تسمية',
            'common.delete':  'حذف',
            'common.move':    'نقل',
            'common.none':    'بدون',
            'common.folder_name': 'اسم المجلد',
            'common.folder_icon': 'أيقونة أو رمز المجلد',
            'common.folder_color':'لون المجلد',
            'common.move_to': 'نقل إلى',
        }
    };

    let currentLang = 'en';

    function t(key, vars) {
        const dict = STRINGS[currentLang] || STRINGS.en;
        let s = dict[key];
        if (s == null) s = STRINGS.en[key];
        if (s == null) return key;
        if (vars) {
            s = s.replace(/\{(\w+)\}/g, (_, k) => (vars[k] != null ? String(vars[k]) : ''));
        }
        return s;
    }

    function applyDomI18n(scope) {
        const sc = scope || document;
        sc.querySelectorAll('[data-i18n]').forEach((el) => {
            const key = el.getAttribute('data-i18n');
            if (key) el.textContent = t(key);
        });
        sc.querySelectorAll('[data-i18n-attr]').forEach((el) => {
            const spec = el.getAttribute('data-i18n-attr');
            if (!spec) return;
            spec.split(',').forEach((pair) => {
                const [attr, key] = pair.split(':').map((s) => s.trim());
                if (attr && key) el.setAttribute(attr, t(key));
            });
        });
    }

    function applyLanguage(lang, opts) {
        opts = opts || {};
        const next = (lang === 'ar') ? 'ar' : 'en';
        currentLang = next;
        root.setAttribute('lang', next);
        root.setAttribute('dir', next === 'ar' ? 'rtl' : 'ltr');
        try { localStorage.setItem(LANG_KEY, next); } catch (_) {}
        // Setup dayjs locale (best-effort)
        if (window.dayjs && window.dayjs.locale) {
            try { window.dayjs.locale(next === 'ar' ? 'ar' : 'en'); } catch (_) {}
        }
        applyDomI18n();
        if (window.lucide && window.lucide.createIcons) {
            try { window.lucide.createIcons(); } catch (_) {}
        }
        document.dispatchEvent(new CustomEvent('ih4x:lang', { detail: { lang: next } }));
        if (!opts.skipPersist) {
            // Best-effort persist
            try {
                fetchJson('api.php?action=settings', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ language: next })
                });
            } catch (_) {}
        }
    }

    function toggleLanguage() {
        applyLanguage(currentLang === 'ar' ? 'en' : 'ar');
    }

    function getLanguage() { return currentLang; }

    /* =====================================================================
       Theme
       ===================================================================== */

    function applyTheme(theme, opts) {
        opts = opts || {};
        const saved = theme || localStorage.getItem(THEME_KEY) || (matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark');
        root.setAttribute('data-theme', saved);
        if (theme) {
            try { localStorage.setItem(THEME_KEY, theme); } catch (_) {}
            if (!opts.skipPersist) {
                try {
                    fetchJson('api.php?action=settings', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ theme: saved })
                    });
                } catch (_) {}
            }
        }
        const hljsLight = document.getElementById('hljs-light');
        const hljsDark  = document.getElementById('hljs-dark');
        if (hljsLight && hljsDark) {
            hljsLight.disabled = saved !== 'light';
            hljsDark.disabled  = saved === 'light';
        }
    }
    function toggleTheme() {
        const current = root.getAttribute('data-theme') === 'light' ? 'light' : 'dark';
        applyTheme(current === 'light' ? 'dark' : 'light');
    }
    function getTheme() {
        return root.getAttribute('data-theme') === 'light' ? 'light' : 'dark';
    }

    /* =====================================================================
       Fetch / sanitize / time
       ===================================================================== */

    let csrfToken = null;
    let csrfPromise = null;

    async function ensureCsrfToken() {
        if (csrfToken) return csrfToken;
        if (!csrfPromise) {
            csrfPromise = fetch('api.php?r=csrf')
                .then((response) => response.ok ? response.json() : null)
                .then((payload) => {
                    if (payload && payload.csrfToken) csrfToken = payload.csrfToken;
                    return csrfToken;
                })
                .finally(() => { csrfPromise = null; });
        }
        return csrfPromise;
    }

    async function fetchJson(url, options) {
        const opts = Object.assign({}, options || {});
        const method = String(opts.method || 'GET').toUpperCase();
        if (method !== 'GET' && method !== 'HEAD') {
            const token = await ensureCsrfToken();
            opts.headers = Object.assign({}, opts.headers || {}, token ? { 'X-CSRF-Token': token } : {});
        }
        const response = await fetch(url, opts);
        let payload = null;
        try { payload = await response.json(); } catch (_) {}
        if (payload && payload.csrfToken) csrfToken = payload.csrfToken;
        if (!response.ok || !payload || payload.success === false) {
            const msg = (payload && (payload.message || payload.error)) || ('Request failed (' + response.status + ').');
            throw new Error(msg);
        }
        return payload;
    }

    function relTime(date) {
        if (!date) return '';
        if (window.dayjs && window.dayjs.extend) {
            try { return window.dayjs(date).fromNow(); } catch (_) {}
        }
        const d = new Date(date);
        const diff = (Date.now() - d.getTime()) / 1000;
        if (diff < 60) return t('status.just_now');
        if (diff < 3600) return Math.floor(diff / 60) + 'm';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h';
        if (diff < 86400 * 7) return Math.floor(diff / 86400) + 'd';
        return d.toLocaleDateString();
    }

    function sanitize(html) {
        if (window.DOMPurify) {
            return window.DOMPurify.sanitize(String(html || ''), {
                ADD_ATTR: ['contenteditable', 'data-checked', 'data-lang']
            });
        }
        return String(html || '');
    }

    /* =====================================================================
       Toasts
       ===================================================================== */

    let toastHost = null;
    function ensureToastHost() {
        if (!toastHost || !document.body.contains(toastHost)) {
            toastHost = document.createElement('div');
            toastHost.className = 'toast-host';
            document.body.appendChild(toastHost);
        }
        return toastHost;
    }
    function toast(message, opts) {
        opts = opts || {};
        const host = ensureToastHost();
        const el = document.createElement('div');
        el.className = 'toast toast-' + (opts.type || 'info');
        const msg = document.createElement('span');
        msg.textContent = message;
        el.appendChild(msg);
        if (opts.action && opts.action.label) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'toast-action';
            btn.textContent = opts.action.label;
            btn.addEventListener('click', () => {
                try { opts.action.onClick && opts.action.onClick(); } finally { dismiss(); }
            });
            el.appendChild(btn);
        }
        const close = document.createElement('button');
        close.type = 'button';
        close.className = 'toast-close';
        close.setAttribute('aria-label', t('toast.dismiss'));
        close.innerHTML = '&times;';
        close.addEventListener('click', dismiss);
        el.appendChild(close);
        host.appendChild(el);
        const timer = setTimeout(dismiss, opts.timeout || 4200);
        function dismiss() {
            clearTimeout(timer);
            el.classList.add('toast-leave');
            setTimeout(() => el.remove(), 250);
        }
        return dismiss;
    }

    /* =====================================================================
       Confirm modal
       ===================================================================== */

    function confirmModal(opts) {
        opts = opts || {};
        return new Promise((resolve) => {
            const overlay = document.createElement('div');
            overlay.className = 'modal-overlay';
            overlay.setAttribute('role', 'dialog');
            overlay.setAttribute('aria-modal', 'true');
            overlay.innerHTML = `
                <div class="modal" tabindex="-1">
                    <h3 class="modal-title"></h3>
                    <p class="modal-body" style="white-space:pre-wrap"></p>
                    <div class="modal-actions">
                        <button class="btn-ghost" data-act="cancel"></button>
                        <button class="btn-primary" data-act="ok"></button>
                    </div>
                </div>`;
            overlay.querySelector('.modal-title').textContent = opts.title || t('common.confirm');
            overlay.querySelector('.modal-body').textContent  = opts.message || '';
            overlay.querySelector('[data-act="cancel"]').textContent = opts.cancelLabel || t('common.cancel');
            const okBtn = overlay.querySelector('[data-act="ok"]');
            okBtn.textContent = opts.okLabel || t('common.confirm');
            if (opts.danger) okBtn.classList.add('btn-danger');

            function close(result) {
                document.removeEventListener('keydown', onKey);
                overlay.remove();
                resolve(result);
            }
            function onKey(e) {
                if (e.key === 'Escape') close(false);
                if (e.key === 'Enter') close(true);
            }
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) close(false);
                const act = e.target.closest('[data-act]');
                if (!act) return;
                close(act.dataset.act === 'ok');
            });
            overlay.addEventListener('keydown', trapFocus);
            document.addEventListener('keydown', onKey);
            document.body.appendChild(overlay);
            setTimeout(() => okBtn.focus(), 10);
        });
    }

    function trapFocus(e) {
        if (e.key !== 'Tab') return;
        const r = e.currentTarget;
        const focusables = r.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
        if (!focusables.length) return;
        const first = focusables[0];
        const last  = focusables[focusables.length - 1];
        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    }

    /* =====================================================================
       Settings modal
       ===================================================================== */

    function openSettings() {
        const overlay = document.createElement('div');
        overlay.className = 'modal-overlay';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.innerHTML = `
            <div class="modal settings-modal" tabindex="-1" style="max-width:480px">
                <h3 class="modal-title">${escapeHtml(t('settings.title'))}</h3>

                <div class="settings-section">
                    <div class="settings-section-title">${escapeHtml(t('settings.appearance'))}</div>

                    <div class="settings-row">
                        <label class="settings-label">${escapeHtml(t('settings.language'))}</label>
                        <div class="settings-options">
                            <label class="settings-opt"><input type="radio" name="lang" value="en"> ${escapeHtml(t('settings.lang_en'))}</label>
                            <label class="settings-opt"><input type="radio" name="lang" value="ar"> ${escapeHtml(t('settings.lang_ar'))}</label>
                        </div>
                    </div>

                    <div class="settings-row">
                        <label class="settings-label">${escapeHtml(t('settings.theme'))}</label>
                        <div class="settings-options">
                            <label class="settings-opt"><input type="radio" name="theme" value="dark"> ${escapeHtml(t('settings.theme_dark'))}</label>
                            <label class="settings-opt"><input type="radio" name="theme" value="light"> ${escapeHtml(t('settings.theme_light'))}</label>
                        </div>
                    </div>
                </div>

                <div class="modal-actions">
                    <button class="btn-primary" data-act="close">${escapeHtml(t('settings.close'))}</button>
                </div>
            </div>`;

        const langInputs = overlay.querySelectorAll('input[name="lang"]');
        const themeInputs = overlay.querySelectorAll('input[name="theme"]');
        langInputs.forEach((i) => { i.checked = i.value === currentLang; });
        themeInputs.forEach((i) => { i.checked = i.value === getTheme(); });

        langInputs.forEach((i) => i.addEventListener('change', () => { if (i.checked) applyLanguage(i.value); }));
        themeInputs.forEach((i) => i.addEventListener('change', () => { if (i.checked) applyTheme(i.value); }));

        function close() {
            document.removeEventListener('keydown', onKey);
            overlay.remove();
        }
        function onKey(e) { if (e.key === 'Escape') close(); }

        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) close();
            if (e.target.closest('[data-act="close"]')) close();
        });
        overlay.addEventListener('keydown', trapFocus);
        document.addEventListener('keydown', onKey);
        document.body.appendChild(overlay);
        setTimeout(() => overlay.querySelector('.btn-primary').focus(), 10);
    }

    /* =====================================================================
       Shortcuts modal
       ===================================================================== */

    function openShortcuts(sections) {
        const overlay = document.createElement('div');
        overlay.className = 'modal-overlay';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');

        const sectionsHtml = (sections || []).map((sec) => {
            const rowsHtml = (sec.rows || []).map((row) => {
                const main = renderKeys(row.keys || []);
                const alt = row.alt && row.alt.length
                    ? '<span class="alt-sep">' + escapeHtml(t('shortcuts.or') || 'or') + '</span>' + renderKeys(row.alt)
                    : '';
                return '<div class="shortcut-row">'
                    +     '<span class="shortcut-label">' + escapeHtml(row.label || '') + '</span>'
                    +     '<span class="shortcut-keys" dir="ltr">' + main + alt + '</span>'
                    + '</div>';
            }).join('');
            return '<div class="shortcuts-section">'
                +     '<h4>' + escapeHtml(sec.title || '') + '</h4>'
                +     rowsHtml
                + '</div>';
        }).join('');

        overlay.innerHTML = ''
            + '<div class="modal shortcuts-modal" tabindex="-1">'
            +     '<h3 class="modal-title">' + escapeHtml(t('shortcuts.title')) + '</h3>'
            +     '<div class="shortcuts-grid">' + sectionsHtml + '</div>'
            +     '<div class="modal-actions">'
            +         '<button class="btn-primary" data-act="close">' + escapeHtml(t('shortcuts.got_it')) + '</button>'
            +     '</div>'
            + '</div>';

        function close() {
            document.removeEventListener('keydown', onKey);
            overlay.remove();
        }
        function onKey(e) { if (e.key === 'Escape' || e.key === 'Enter') { e.preventDefault(); close(); } }
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) close();
            if (e.target.closest('[data-act="close"]')) close();
        });
        overlay.addEventListener('keydown', trapFocus);
        document.addEventListener('keydown', onKey);
        document.body.appendChild(overlay);
        setTimeout(() => overlay.querySelector('.btn-primary').focus(), 10);
    }

    function renderKeys(keys) {
        return keys.map((k, i) => {
            const sep = i > 0 ? '<span class="plus">+</span>' : '';
            return sep + '<kbd>' + escapeHtml(k) + '</kbd>';
        }).join('');
    }

    /* =====================================================================
       Hotkeys
       ===================================================================== */

    const hotkeys = [];
    function parseCombo(combo) {
        const parts = combo.toLowerCase().split('+').map(s => s.trim());
        return {
            ctrl: parts.includes('ctrl') || parts.includes('mod'),
            meta: parts.includes('meta') || parts.includes('mod'),
            shift: parts.includes('shift'),
            alt: parts.includes('alt'),
            key: parts[parts.length - 1]
        };
    }
    function registerHotkey(combo, fn, opts) {
        hotkeys.push({ combo: parseCombo(combo), fn, opts: opts || {} });
    }
    document.addEventListener('keydown', (e) => {
        const tag = (e.target && e.target.tagName) || '';
        const inEditable = e.target && (e.target.isContentEditable || tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT');
        const key = (e.key || '').toLowerCase();
        for (const h of hotkeys) {
            const c = h.combo;
            const modOk = (c.ctrl || c.meta) ? (e.ctrlKey || e.metaKey) : (!e.ctrlKey && !e.metaKey);
            if (!modOk) continue;
            if (!!c.shift !== e.shiftKey) continue;
            if (!!c.alt !== e.altKey) continue;
            if (c.key !== key) continue;
            if (inEditable && !h.opts.allowInInputs && !(c.ctrl || c.meta)) continue;
            e.preventDefault();
            h.fn(e);
            return;
        }
    });

    /* =====================================================================
       Command palette
       ===================================================================== */

    function fuzzyScore(needle, hay) {
        needle = needle.toLowerCase();
        hay = hay.toLowerCase();
        if (!needle) return 1;
        if (hay.includes(needle)) return 100 - (hay.indexOf(needle));
        let n = 0, score = 0, last = -1;
        for (let i = 0; i < hay.length && n < needle.length; i++) {
            if (hay[i] === needle[n]) {
                score += last === i - 1 ? 3 : 1;
                last = i;
                n++;
            }
        }
        return n === needle.length ? score : 0;
    }
    function openPalette(items, opts) {
        opts = opts || {};
        const overlay = document.createElement('div');
        overlay.className = 'modal-overlay cmdk-overlay';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        const placeholder = opts.placeholder || t('palette.placeholder');
        overlay.innerHTML = `
            <div class="cmdk">
                <input class="cmdk-input" type="text" placeholder="${escapeHtml(placeholder)}" aria-label="${escapeHtml(t('palette.aria'))}" />
                <ul class="cmdk-list" role="listbox"></ul>
                <div class="cmdk-hint"><kbd>↑</kbd><kbd>↓</kbd> ${escapeHtml(t('palette.hint_navigate'))} <kbd>Enter</kbd> ${escapeHtml(t('palette.hint_select'))} <kbd>Esc</kbd> ${escapeHtml(t('palette.hint_close'))}</div>
            </div>`;
        const input = overlay.querySelector('.cmdk-input');
        const list = overlay.querySelector('.cmdk-list');
        let active = 0;
        let filtered = items.slice();

        function render() {
            list.innerHTML = '';
            filtered.forEach((it, idx) => {
                const li = document.createElement('li');
                li.className = 'cmdk-item' + (idx === active ? ' active' : '');
                li.setAttribute('role', 'option');
                li.innerHTML = `
                    <span class="cmdk-icon">${it.icon || '•'}</span>
                    <span class="cmdk-label">${escapeHtml(it.label)}</span>
                    ${it.hint ? `<span class="cmdk-hint-text">${escapeHtml(it.hint)}</span>` : ''}
                `;
                li.addEventListener('click', () => choose(idx));
                list.appendChild(li);
            });
        }
        function update() {
            const q = input.value.trim();
            filtered = items
                .map(it => ({ it, s: fuzzyScore(q, (it.label || '') + ' ' + (it.hint || '')) }))
                .filter(x => x.s > 0)
                .sort((a, b) => b.s - a.s)
                .map(x => x.it);
            active = 0;
            render();
        }
        function choose(idx) {
            const item = filtered[idx];
            close();
            if (item && item.run) item.run();
        }
        function close() {
            document.removeEventListener('keydown', onKey);
            overlay.remove();
        }
        function onKey(e) {
            if (e.key === 'Escape') { e.preventDefault(); close(); }
            else if (e.key === 'ArrowDown') { e.preventDefault(); active = Math.min(filtered.length - 1, active + 1); render(); scrollActive(); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); active = Math.max(0, active - 1); render(); scrollActive(); }
            else if (e.key === 'Enter') { e.preventDefault(); choose(active); }
        }
        function scrollActive() {
            const el = list.querySelector('.cmdk-item.active');
            if (el) el.scrollIntoView({ block: 'nearest' });
        }
        overlay.addEventListener('click', (e) => { if (e.target === overlay) close(); });
        overlay.addEventListener('keydown', trapFocus);
        input.addEventListener('input', update);
        document.addEventListener('keydown', onKey);
        document.body.appendChild(overlay);
        update();
        setTimeout(() => input.focus(), 10);
    }

    function escapeHtml(s) {
        return String(s || '').replace(/[&<>"']/g, (m) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));
    }

    function userInitials(displayName) {
        return String(displayName || '?')
            .trim()
            .split(/\s+/)
            .slice(0, 2)
            .map((part) => part.charAt(0))
            .join('')
            .toUpperCase() || '?';
    }

    function userColor(username) {
        const seed = String(username || 'ih4x');
        let hash = 0;
        for (let i = 0; i < seed.length; i++) hash = ((hash << 5) - hash) + seed.charCodeAt(i);
        const palette = ['#7aa7ff', '#10b981', '#f59e0b', '#8b5cf6', '#f43f5e', '#0ea5e9'];
        return palette[Math.abs(hash) % palette.length];
    }

    function setupDayjs() {
        if (window.dayjs && window.dayjs_plugin_relativeTime) {
            window.dayjs.extend(window.dayjs_plugin_relativeTime);
        }
    }

    /* =====================================================================
       Boot
       ===================================================================== */

    // Apply quickly from localStorage to avoid flash
    applyTheme();
    const savedLang = (function () {
        try { return localStorage.getItem(LANG_KEY); } catch (_) { return null; }
    })();
    if (savedLang === 'ar' || savedLang === 'en') {
        applyLanguage(savedLang, { skipPersist: true });
    } else {
        applyLanguage('en', { skipPersist: true });
    }

    document.addEventListener('DOMContentLoaded', () => {
        setupDayjs();
        applyDomI18n(); // re-apply once DOM is fully ready
        if (window.lucide && window.lucide.createIcons) {
            try { window.lucide.createIcons(); } catch (_) {}
        }
        // Pull authoritative server settings
        fetchJson('api.php?action=settings')
            .then((j) => {
                if (j && j.success && j.settings) {
                    if (j.settings.language && j.settings.language !== currentLang) {
                        applyLanguage(j.settings.language, { skipPersist: true });
                    }
                    if (j.settings.theme && j.settings.theme !== getTheme()) {
                        applyTheme(j.settings.theme, { skipPersist: true });
                    }
                }
            })
            .catch(() => {});
    });

    window.iH4x = {
        applyTheme, toggleTheme, getTheme,
        applyLanguage, toggleLanguage, getLanguage,
        t, applyDomI18n,
        fetchJson,
        toast, confirmModal,
        openSettings,
        openShortcuts,
        registerHotkey,
        openPalette,
        relTime,
        sanitize,
        escapeHtml,
        userInitials,
        userColor,
    };
})();
