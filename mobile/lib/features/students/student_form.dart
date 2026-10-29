import '../../core/operation_id.dart';
import '../../core/money.dart';
import '../finance/student_fee_screen.dart';

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
  final sectionRows = List<dynamic>.from(results[0]['items'] as List);
  final current = results[2]['current_academic_session_id'];
  if (current != null) {
    sectionRows.sort(
      (a, b) => (a['academic_session_id'] == current ? 0 : 1).compareTo(
        b['academic_session_id'] == current ? 0 : 1,
      ),
    );
  }
  return {
    for (final v in sectionRows)
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
  Map<String, String> methods = {};
  try {
    if (existing == null) {
      sections = await sectionOptions(auth);
      if (auth.can('finance.write')) {
        final result = await auth.call('finance/payment-methods');
        methods = {
          for (final row in result['items'] as List)
            row['id'] as String: row['name'] as String,
        };
      }
    }
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
  final requestId = operationId();
  bool hadFinance = false;
  bool receiving(Map<String, dynamic> v) =>
      (Money.tryParse('${v['_received'] ?? ''}') ?? 0) > 0;
  final saved = await Navigator.push<bool>(
    context,
    MaterialPageRoute(
      builder: (_) => DomainForm(
        title: existing == null ? 'admitStudent' : 'editStudent',
        summary: existing == null
            ? const Text(
                'Fee setup and payment are separate. Only record an amount received when money was actually collected. Vouchers and receipts use the normal school finance workflow; missing months can be previewed later.',
              )
            : null,
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
          if (existing == null && auth.can('finance.configure'))
            const FormFieldSpec(
              '_tuition_override',
              'Agreed monthly tuition (optional override)',
              section: 'Initial finance setup',
              kind: FieldKind.money,
              required: false,
            ),
          if (existing == null && auth.can('finance.write')) ...[
            const FormFieldSpec(
              '_admission_fee',
              'Generate configured admission fee',
              section: 'Initial fees and payment (manual)',
              kind: FieldKind.boolean,
              initial: false,
            ),
            const FormFieldSpec(
              '_first_tuition',
              'Generate first month tuition voucher',
              kind: FieldKind.boolean,
              initial: false,
            ),
            const FormFieldSpec(
              '_charge_description',
              'Other one-time fee description',
              required: false,
            ),
            const FormFieldSpec(
              '_charge_amount',
              'Other one-time fee amount',
              kind: FieldKind.money,
              required: false,
            ),
            const FormFieldSpec(
              '_opening',
              'Opening / historical dues (explicit balance)',
              kind: FieldKind.money,
              required: false,
            ),
            const FormFieldSpec(
              '_received',
              'Actual amount received at admission',
              kind: FieldKind.money,
              required: false,
            ),
            FormFieldSpec(
              '_method',
              'Manual payment method',
              kind: FieldKind.choice,
              options: methods,
              visibleWhen: receiving,
            ),
            FormFieldSpec(
              '_paid_on',
              'Date money was received',
              kind: FieldKind.date,
              initial: DateTime.now().toIso8601String().substring(0, 10),
              visibleWhen: receiving,
            ),
            FormFieldSpec(
              '_reference',
              'Optional receipt/reference',
              required: false,
              visibleWhen: receiving,
            ),
            FormFieldSpec(
              '_notes',
              'Payment notes',
              kind: FieldKind.multiline,
              required: false,
              maxLength: 500,
              visibleWhen: receiving,
            ),
          ],
        ],
        onSubmit: (v) async {
          final photo = v.remove('_photo') as ProfilePhotoDraft?;
          final finance = <String, dynamic>{};
          final override = v.remove('_tuition_override');
          if (override != null) finance['tuition_override_minor'] = override;
          if (v.remove('_admission_fee') == true) {
            finance['admission_fee'] = true;
          }
          if (v.remove('_first_tuition') == true) {
            finance['bill_tuition'] = true;
          }
          final charge = v.remove('_charge_amount') as int?;
          final description = v.remove('_charge_description');
          if (charge != null && charge > 0) {
            finance['charges'] = [
              {
                'description': description ?? 'Other admission charge',
                'amount_minor': charge,
              },
            ];
          }
          final opening = v.remove('_opening') as int?;
          if (opening != null && opening > 0) {
            finance['opening_balance_minor'] = opening;
          }
          final received = v.remove('_received') as int?;
          final method = v.remove('_method');
          final date = v.remove('_paid_on');
          final reference = v.remove('_reference');
          final notes = v.remove('_notes');
          if (received != null && received > 0) {
            finance['payment'] = {
              'amount_minor': received,
              'payment_method_id': method,
              'paid_on': date,
              'reference': reference,
              'notes': notes,
            };
          }
          hadFinance = finance.isNotEmpty;
          if (savedId == null) {
            v['request_id'] = requestId;
            if (hadFinance) v['admission_finance'] = finance;
          }

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
  if (saved == true &&
      existing == null &&
      hadFinance &&
      context.mounted &&
      auth.can('finance.read')) {
    await Navigator.push<void>(
      context,
      MaterialPageRoute(
        builder: (_) => StudentFeeScreen(auth: auth, studentId: savedId!),
      ),
    );
  }
  return saved;
}
