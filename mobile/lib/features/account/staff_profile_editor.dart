import 'package:flutter/material.dart';

import '../../core/api_client.dart';
import '../../design/app_theme.dart';
import '../../l10n/strings.dart';
import '../../shared/forms/profile_photo_picker.dart';
import '../../shared/widgets/surface_card.dart';
import '../auth/auth_controller.dart';

class StaffProfileEditor extends StatefulWidget {
  const StaffProfileEditor({
    super.key,
    required this.auth,
    required this.account,
  });
  final AuthController auth;
  final Map<String, dynamic> account;
  @override
  State<StaffProfileEditor> createState() => _StaffProfileEditorState();
}

class _StaffProfileEditorState extends State<StaffProfileEditor> {
  final form = GlobalKey<FormState>();
  late final TextEditingController name, mobile, address;
  late ProfilePhotoDraft photo;
  bool busy = false;
  String? error;
  @override
  void initState() {
    super.initState();
    name = TextEditingController(text: widget.account['name'] as String?);
    mobile = TextEditingController(text: widget.account['mobile'] as String?);
    address = TextEditingController(text: widget.account['address'] as String?);
    photo = ProfilePhotoDraft(
      existingId: widget.account['photo_file_id'] as String?,
    );
  }

  @override
  void dispose() {
    name.dispose();
    mobile.dispose();
    address.dispose();
    super.dispose();
  }

  Future<void> save() async {
    if (busy || !form.currentState!.validate()) return;
    setState(() => busy = true);
    try {
      await widget.auth.call(
        'account/profile',
        method: 'PATCH',
        body: {
          'name': name.text.trim(),
          'mobile': mobile.text.trim().isEmpty ? null : mobile.text.trim(),
          'address': address.text.trim().isEmpty ? null : address.text.trim(),
        },
      );
      await saveProfilePhoto(
        widget.auth,
        photo,
        widget.account['id'] as String,
        staff: true,
      );
      if (mounted) Navigator.pop(context, true);
    } on ApiFailure catch (e) {
      if (mounted) setState(() => error = e.code);
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = Strings.of(context);
    return Scaffold(
      appBar: AppBar(title: Text(s.text('accountEditProfile'))),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(AppSpace.lg),
        child: SurfaceCard(
          child: Form(
            key: form,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                StatusBanner(message: s.text('accountEditNote')),
                const SizedBox(height: AppSpace.lg),
                ProfilePhotoPicker(
                  auth: widget.auth,
                  value: photo,
                  enabled: !busy,
                  onChanged: (v) => setState(() => photo = v),
                ),
                const SizedBox(height: AppSpace.lg),
                TextFormField(
                  key: const Key('own-name'),
                  controller: name,
                  enabled: !busy,
                  maxLength: 200,
                  decoration: InputDecoration(
                    labelText: s.text('fullName'),
                    prefixIcon: const Icon(Icons.person_outline),
                  ),
                  validator: (v) => v == null || v.trim().isEmpty
                      ? s.text('fieldRequired')
                      : null,
                ),
                const SizedBox(height: AppSpace.md),
                TextFormField(
                  key: const Key('own-mobile'),
                  controller: mobile,
                  enabled: !busy,
                  maxLength: 30,
                  keyboardType: TextInputType.phone,
                  decoration: InputDecoration(
                    labelText: s.text('mobile'),
                    prefixIcon: const Icon(Icons.phone_outlined),
                  ),
                  validator: (v) =>
                      v != null &&
                          v.trim().isNotEmpty &&
                          !RegExp(r'^\+?(?:[ ()-]*[0-9]){7,15}[ ()-]*$')
                              .hasMatch(v.trim())
                      ? s.text('accountMobileInvalid')
                      : null,
                ),
                const SizedBox(height: AppSpace.md),
                TextFormField(
                  key: const Key('own-address'),
                  controller: address,
                  enabled: !busy,
                  maxLength: 1000,
                  maxLines: 3,
                  decoration: InputDecoration(
                    labelText: s.text('address'),
                    prefixIcon: const Icon(Icons.location_on_outlined),
                  ),
                ),
                if (error != null)
                  StatusBanner(message: s.text(error!), kind: StatusKind.error),
                const SizedBox(height: AppSpace.lg),
                FilledButton.icon(
                  key: const Key('save-own-profile'),
                  onPressed: busy ? null : save,
                  icon: busy
                      ? const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Icon(Icons.save_outlined),
                  label: Text(s.text('save')),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
