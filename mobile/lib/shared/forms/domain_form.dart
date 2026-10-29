import 'package:flutter/material.dart';

import '../../core/api_client.dart';
import 'profile_photo_picker.dart';
import '../../core/money.dart';
import '../../design/app_theme.dart';
import '../../l10n/strings.dart';
import '../widgets/surface_card.dart';
import '../widgets/polished_components.dart';
import '../widgets/academic_filters.dart';
import '../../features/auth/auth_controller.dart';

enum FieldKind {
  text,
  multiline,
  integer,
  money,
  percent,
  date,
  choice,
  multichoice,
  boolean,
  password,
  attachment,
  photo,
}

class FormFieldSpec {
  const FormFieldSpec(
    this.name,
    this.label, {
    this.kind = FieldKind.text,
    this.required = true,
    this.options = const {},
    this.initial,
    this.maxLength = 200,
    this.picker,
    this.photoAuth,
    this.section,
    this.visibleWhen,
  });
  final AuthController? photoAuth;
  final String? section;
  final bool Function(Map<String, dynamic>)? visibleWhen;
  final String name, label;
  final FieldKind kind;
  final bool required;
  final Map<String, String> options;
  final dynamic initial;
  final int maxLength;
  final Future<String?> Function()? picker;
}

class DomainForm extends StatefulWidget {
  const DomainForm({
    super.key,
    required this.title,
    required this.fields,
    required this.onSubmit,
    this.academicAuth,
    this.summary,
  });
  final String title;
  final Widget? summary;
  final AuthController? academicAuth;
  final List<FormFieldSpec> fields;
  final Future<void> Function(Map<String, dynamic>) onSubmit;
  @override
  State<DomainForm> createState() => _DomainFormState();
}

class _DomainFormState extends State<DomainForm> {
  final form = GlobalKey<FormState>();
  final controllers = <String, TextEditingController>{};
  final values = <String, dynamic>{};
  bool busy = false;
  String? error;
  Map<String, dynamic> fieldErrors = {};
  String? fieldError(String key) {
    final raw = fieldErrors[key];
    return raw is List ? raw.firstOrNull?.toString() : raw?.toString();
  }

  @override
  void initState() {
    super.initState();
    for (final f in widget.fields) {
      values[f.name] = f.kind == FieldKind.photo
          ? ProfilePhotoDraft(existingId: f.initial as String?)
          : f.initial;
      if (![
        FieldKind.choice,
        FieldKind.multichoice,
        FieldKind.boolean,
        FieldKind.attachment,
        FieldKind.photo,
      ].contains(f.kind)) {
        controllers[f.name] = TextEditingController(
          text: f.initial == null
              ? ''
              : (f.kind == FieldKind.money || f.kind == FieldKind.percent)
              ? Money.numeric(num.parse(f.initial.toString()))
              : f.initial.toString(),
        );
      }
    }
  }

  @override
  void dispose() {
    for (final c in controllers.values) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> save() async {
    if (!form.currentState!.validate() || busy) return;
    if (widget.academicAuth != null &&
        widget.fields.any(
          (f) =>
              ['section_id', 'subject_id'].contains(f.name) &&
              f.required &&
              values[f.name] == null,
        )) {
      setState(() => error = 'fieldRequired');
      return;
    }
    FocusScope.of(context).unfocus();
    setState(() {
      busy = true;
      error = null;
      fieldErrors = {};
    });
    final data = <String, dynamic>{};
    for (final f in widget.fields) {
      if (f.visibleWhen != null && !f.visibleWhen!(values)) continue;
      final value =
          (f.kind == FieldKind.password
              ? controllers[f.name]?.text
              : controllers[f.name]?.text.trim()) ??
          values[f.name];
      if (value == null || value == '') {
        if (f.required) continue;
        data[f.name] = null;
        continue;
      }
      data[f.name] = [FieldKind.money, FieldKind.percent].contains(f.kind)
          ? Money.tryParse(value.toString())!
          : f.kind == FieldKind.integer
          ? int.parse(value.toString())
          : f.kind == FieldKind.boolean
          ? (value == true)
          : value;
    }
    try {
      await widget.onSubmit(data);
      if (mounted) Navigator.pop(context, true);
    } on ApiFailure catch (e) {
      if (mounted) {
        setState(() {
          error = e.code;
          fieldErrors = Map<String, dynamic>.of(e.fields);
        });
      }
    } catch (_) {
      if (mounted) setState(() => error = 'server');
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = Strings.of(context);
    return Scaffold(
      appBar: AppBar(title: Text(s.text(widget.title))),
      body: SafeArea(
        child: SingleChildScrollView(
          keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
          padding: const EdgeInsets.all(AppSpace.lg),
          child: Center(
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 640),
              child: SurfaceCard(
                child: Form(
                  key: form,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      SectionHeader(title: s.text(widget.title)),
                      if (widget.summary != null) widget.summary!,
                      if (widget.academicAuth != null)
                        AcademicSelection(
                          preferCurrentSession: true,
                          auth: widget.academicAuth!,
                          initial: {
                            for (final k in ['section_id', 'subject_id'])
                              if (values[k] != null) k: values[k] as String,
                          },
                          subject: widget.fields.any(
                            (f) => f.name == 'subject_id',
                          ),
                          onChanged: (v) => setState(() {
                            values['section_id'] = v['section_id'];
                            values['subject_id'] = v['subject_id'];
                          }),
                        ),
                      for (final f in widget.fields)
                        if ((f.visibleWhen == null || f.visibleWhen!(values)) &&
                            (widget.academicAuth == null ||
                                !['section_id', 'subject_id'].contains(f.name)))
                          Padding(
                            key: ValueKey(f.name),
                            padding: const EdgeInsets.only(bottom: AppSpace.lg),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.stretch,
                              children: [
                                if (f.section != null)
                                  SectionHeader(title: s.text(f.section!)),
                                field(f, s),
                              ],
                            ),
                          ),
                      for (final key in fieldErrors.keys)
                        if (!widget.fields.any((f) => f.name == key))
                          Padding(
                            padding: const EdgeInsets.only(bottom: AppSpace.sm),
                            child: Text(
                              fieldError(key) ?? '',
                              style: TextStyle(
                                color: Theme.of(context).colorScheme.error,
                              ),
                            ),
                          ),
                      if (error != null) ...[
                        StatusBanner(
                          message: s.text(error!),
                          kind: StatusKind.error,
                        ),
                        const SizedBox(height: AppSpace.lg),
                      ],
                      FilledButton(
                        onPressed: busy ? null : save,
                        child: busy
                            ? const SizedBox(
                                width: 20,
                                height: 20,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2,
                                ),
                              )
                            : Text(s.text('save')),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget field(FormFieldSpec f, Strings s) {
    if (f.kind == FieldKind.photo) {
      return ProfilePhotoPicker(
        auth: f.photoAuth!,
        value: values[f.name] as ProfilePhotoDraft,
        enabled: !busy,
        onChanged: (v) => setState(() => values[f.name] = v),
      );
    }
    if (f.kind == FieldKind.attachment) {
      return Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          OutlinedButton.icon(
            onPressed: busy || f.picker == null
                ? null
                : () async {
                    setState(() => busy = true);
                    try {
                      final id = await f.picker!();
                      if (id != null && mounted) {
                        setState(() => values[f.name] = id);
                      }
                    } on ApiFailure catch (e) {
                      if (mounted) {
                        setState(() {
                          error = e.code;
                          fieldErrors = Map<String, dynamic>.of(e.fields);
                        });
                      }
                    } catch (_) {
                      if (mounted) setState(() => error = 'invalidFile');
                    } finally {
                      if (mounted) setState(() => busy = false);
                    }
                  },
            icon: const Icon(Icons.attach_file),
            label: Text(s.text(f.label)),
          ),
          if (values[f.name] != null)
            ListTile(
              title: Text(s.text('attachmentReady')),
              trailing: IconButton(
                tooltip: s.text('remove'),
                onPressed: busy
                    ? null
                    : () => setState(() => values[f.name] = null),
                icon: const Icon(Icons.close),
              ),
            ),
        ],
      );
    }
    if (f.kind == FieldKind.multichoice) {
      final selected = (values[f.name] as List? ?? []).cast<String>();
      return ExpansionTile(
        title: Text(s.text(f.label)),
        children: [
          for (final option in f.options.entries)
            CheckboxListTile(
              title: Text(option.value),
              value: selected.contains(option.key),
              onChanged: busy
                  ? null
                  : (value) => setState(() {
                      final next = List<String>.from(selected);
                      value == true
                          ? next.add(option.key)
                          : next.remove(option.key);
                      values[f.name] = next;
                    }),
            ),
        ],
      );
    }
    if (f.kind == FieldKind.boolean) {
      return CheckboxListTile(
        title: Text(s.text(f.label)),
        value: values[f.name] == true,
        onChanged: busy
            ? null
            : (v) => setState(() {
                values[f.name] = v;
                fieldErrors.remove(f.name);
              }),
      );
    }
    if (f.kind == FieldKind.choice) {
      return DropdownButtonFormField<String>(
        initialValue: f.options.containsKey(values[f.name])
            ? values[f.name] as String?
            : null,
        isExpanded: true,
        decoration: InputDecoration(
          labelText: s.text(f.label),
          errorText: fieldError(f.name),
          errorMaxLines: 3,
        ),
        items: [
          if (!f.required)
            DropdownMenuItem(value: '', child: Text(s.text('anyValue'))),
          ...f.options.entries.map(
            (e) => DropdownMenuItem(
              value: e.key,
              child: Text(e.value, overflow: TextOverflow.ellipsis),
            ),
          ),
        ],
        onChanged: busy
            ? null
            : (v) => setState(() {
                values[f.name] = v == '' ? null : v;
                fieldErrors.remove(f.name);
              }),
        validator: (v) =>
            f.required && v == null ? s.text('fieldRequired') : null,
      );
    }
    return TextFormField(
      controller: controllers[f.name],
      onChanged: (_) {
        if (fieldErrors.containsKey(f.name)) {
          setState(() => fieldErrors.remove(f.name));
        }
      },
      enabled: !busy,
      textDirection:
          [
            FieldKind.money,
            FieldKind.percent,
            FieldKind.integer,
            FieldKind.date,
          ].contains(f.kind)
          ? TextDirection.ltr
          : null,
      readOnly: f.kind == FieldKind.date,
      obscureText: f.kind == FieldKind.password,
      maxLength: f.maxLength,
      maxLines: f.kind == FieldKind.multiline ? 4 : 1,
      keyboardType:
          [
            FieldKind.integer,
            FieldKind.money,
            FieldKind.percent,
          ].contains(f.kind)
          ? const TextInputType.numberWithOptions(decimal: true, signed: true)
          : f.name == 'email'
          ? TextInputType.emailAddress
          : ['mobile', 'emergency_contact'].contains(f.name)
          ? TextInputType.phone
          : null,
      decoration: InputDecoration(
        labelText: s.text(f.label),
        counterText: '',
        errorText: fieldError(f.name),
        errorMaxLines: 3,
        suffixIcon: f.kind == FieldKind.date
            ? const Icon(Icons.calendar_today_outlined)
            : null,
      ),
      onTap: f.kind == FieldKind.date
          ? () async {
              final date = await showDatePicker(
                context: context,
                initialDate:
                    DateTime.tryParse(controllers[f.name]!.text) ??
                    DateTime.now(),
                firstDate: DateTime(1950),
                lastDate: DateTime(2100),
              );
              if (date != null) {
                controllers[f.name]!.text = date.toIso8601String().substring(
                  0,
                  10,
                );
              }
            }
          : null,
      validator: (v) {
        if (f.required && (v == null || v.trim().isEmpty)) {
          return s.text('fieldRequired');
        }
        if ([FieldKind.money, FieldKind.percent].contains(f.kind) &&
            (v ?? '').isNotEmpty &&
            Money.tryParse(v!) == null) {
          return s.text('moneyInvalid');
        }
        if (f.kind == FieldKind.integer &&
            (v ?? '').isNotEmpty &&
            int.tryParse(v!) == null) {
          return s.text('numberInvalid');
        }
        return null;
      },
    );
  }
}
