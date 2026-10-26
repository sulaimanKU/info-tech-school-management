import 'package:flutter/material.dart';

import '../../l10n/strings.dart';
import '../../shared/forms/profile_photo_picker.dart';
import '../../core/catalog_repository.dart';
import '../../shared/forms/domain_form.dart';
import '../auth/auth_controller.dart';

Future<Map<String, String>> sectionOptions(AuthController auth) async {
  final results = await Future.wait([
    cachedCatalog(auth, 'sections'),
    cachedCatalog(auth, 'classes'),
    cachedCatalog(auth, 'sessions'),
  ]);
  final classes = {
    for (final v in results[1]['items'] as List) (v['id'] as String): v['name'],
  };
  final sessions = {
    for (final v in results[2]['items'] as List) (v['id'] as String): v['name'],
  };
  return {
    for (final v in results[0]['items'] as List)
      (v['id'] as String):
          '${classes[v['class_id']]} · ${v['name']} · ${sessions[v['academic_session_id']]}',
  };
}

Future<bool?> openStudentForm(
  BuildContext context,
  AuthController auth, {
  Map<String, dynamic>? existing,
}) async {
  Map<String, String> sections = {};
  try {
    if (existing == null) sections = await sectionOptions(auth);
  } catch (_) {
    if (context.mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(Strings.of(context).text('network'))),
      );
    }
    return false;
  }
  if (!context.mounted) return false;
  final s = Strings.of(context);
  String? savedId = existing?['id'] as String?;
  return Navigator.push<bool>(
    context,
    MaterialPageRoute(
      builder: (_) => DomainForm(
        title: existing == null ? 'admitStudent' : 'editStudent',
        fields: [
          FormFieldSpec(
            '_photo',
            'profilePhoto',
            kind: FieldKind.photo,
            required: false,
            photoAuth: auth,
            initial: existing?['photo_file_id'],
            section: 'profileSection',
          ),
          FormFieldSpec(
            'full_name',
            'fullName',
            initial: existing?['full_name'],
          ),
          if (existing == null)
            FormFieldSpec(
              'admitted_on',
              'admissionDate',
              kind: FieldKind.date,
              initial: DateTime.now().toIso8601String().substring(0, 10),
            ),
          FormFieldSpec(
            'date_of_birth',
            'dateOfBirth',
            kind: FieldKind.date,
            initial: existing?['date_of_birth'],
          ),
          FormFieldSpec(
            'gender',
            'gender',
            kind: FieldKind.choice,
            initial: existing?['gender'],
            options: {
              'male': s.text('male'),
              'female': s.text('female'),
              'other': s.text('other'),
            },
          ),
          FormFieldSpec(
            'father_name',
            'fatherName',
            required: false,
            initial: existing?['father_name'],
          ),
          FormFieldSpec(
            'address',
            'address',
            section: 'contactSection',
            kind: FieldKind.multiline,
            required: false,
            maxLength: 2000,
            initial: existing?['address'],
          ),
          FormFieldSpec(
            'previous_school',
            'previousSchool',
            required: false,
            initial: existing?['previous_school'],
          ),
          FormFieldSpec(
            'emergency_contact',
            'emergencyContact',
            required: false,
            maxLength: 25,
            initial: existing?['emergency_contact'],
          ),
          if (existing == null) ...[
            FormFieldSpec(
              'section_id',
              'classSection',
              section: 'academicSection',
              kind: FieldKind.choice,
              options: sections,
            ),
            const FormFieldSpec(
              'roll_number',
              'rollNumber',
              kind: FieldKind.integer,
            ),
          ],
        ],
        onSubmit: (v) async {
          final photo = v.remove('_photo') as ProfilePhotoDraft?;
          final result = await auth.call(
            savedId == null ? 'students' : 'students/$savedId',
            body: v,
            method: savedId == null ? 'POST' : 'PATCH',
          );
          savedId ??= result['id'] as String;
          await saveProfilePhoto(auth, photo, savedId!);
        },
      ),
    ),
  );
}
