package com.academylms.academy_app

import android.app.PictureInPictureParams
import android.content.res.Configuration
import android.os.Build
import android.util.Rational
import androidx.annotation.RequiresApi
import io.flutter.embedding.android.FlutterActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.MethodChannel

/**
 * The app's one activity. While a lesson's room is open (the room says so on
 * the `lms/pip` channel: `setEnabled`), leaving the app shrinks it into a
 * small floating window (picture-in-picture, 16:9) instead of hiding it, as
 * a video call does; the room hears `changed` and shows only the main picture.
 */
class MainActivity : FlutterActivity() {
    private var pipEnabled = false
    private var pip: MethodChannel? = null

    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)
        pip = MethodChannel(flutterEngine.dartExecutor.binaryMessenger, "lms/pip").also { channel ->
            channel.setMethodCallHandler { call, result ->
                when (call.method) {
                    "setEnabled" -> {
                        pipEnabled = call.arguments == true
                        applyParams()
                        result.success(Build.VERSION.SDK_INT >= Build.VERSION_CODES.O)
                    }
                    else -> result.notImplemented()
                }
            }
        }
    }

    @RequiresApi(Build.VERSION_CODES.O)
    private fun pipParams(): PictureInPictureParams {
        val builder = PictureInPictureParams.Builder().setAspectRatio(Rational(16, 9))
        // Android 12 and later go small by themselves on the way out, smoothly.
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) builder.setAutoEnterEnabled(pipEnabled)
        return builder.build()
    }

    private fun applyParams() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) return
        try {
            setPictureInPictureParams(pipParams())
        } catch (e: IllegalStateException) {
            // Picture-in-picture is turned off for the app on this phone.
        }
    }

    override fun onUserLeaveHint() {
        super.onUserLeaveHint()
        if (!pipEnabled || Build.VERSION.SDK_INT < Build.VERSION_CODES.O || isInPictureInPictureMode) return
        try {
            enterPictureInPictureMode(pipParams())
        } catch (e: IllegalStateException) {
            // Turned off for the app: it goes to the background as before (the sound goes on).
        }
    }

    @RequiresApi(Build.VERSION_CODES.O)
    override fun onPictureInPictureModeChanged(isInPictureInPictureMode: Boolean, newConfig: Configuration) {
        super.onPictureInPictureModeChanged(isInPictureInPictureMode, newConfig)
        pip?.invokeMethod("changed", isInPictureInPictureMode)
    }

    override fun onDestroy() {
        pip?.setMethodCallHandler(null)
        pip = null
        super.onDestroy()
    }
}
