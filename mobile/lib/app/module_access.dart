import '../features/auth/auth_controller.dart';

abstract final class ModuleAccess {
  static bool allowed(AuthController auth, String module) {
    if (auth.isParent) return false;
    return switch (module) {
      'myClasses' => auth.isTeacher && auth.canAcademics,
      'students' =>
        auth.can('students.read') ||
            auth.can('students.read.all') ||
            auth.can('students.manage'),
      'attendance' || 'academics' => auth.canAcademics,
      'finance' => auth.can('finance.read') || auth.can('payroll.read'),
      'staffModule' => auth.can('staff.manage'),
      'reports' => auth.can('reports.export') || auth.canAcademics,
      'messages' => true,
      'settingsModule' => auth.can('school.manage'),
      'accessControl' => auth.can('rbac.manage'),
      _ => false,
    };
  }
}
